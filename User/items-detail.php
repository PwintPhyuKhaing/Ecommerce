<?php
// items-detail.php – single item detail page (with carousel + seller info + AJAX add-to-cart)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/auth.php';

$logged_in = isset($_SESSION['user_id']);
$user_id   = $logged_in ? (int)$_SESSION['user_id'] : null;

// If an admin opens this page (e.g., from Admin Dashboard),
// we should NOT show customer actions like Buy/Add-to-cart.
$is_admin_view = function_exists('is_admin') ? is_admin() : false;

$error_msg = '';
$info_msg  = '';

$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
if ($item_id <= 0) {
    $error_msg = "Invalid item.";
}

// Fetch item detail + seller username + seller profile photo
$item = null;
$seller_name = '';
$seller_photo = '';

if ($error_msg === '') {
    $stmt = $conn->prepare("
        SELECT i.id, i.seller_id, i.title, i.price, i.image,
               i.category_label, i.item_condition, i.location,
               i.description, i.stock, i.created_at,
               u.username, u.profile_photo
        FROM items i
        LEFT JOIN users u ON i.seller_id = u.id
        WHERE i.id = ? AND i.status = 'active'
        LIMIT 1
    ");

    if (!$stmt) {
        $error_msg = "Database error: " . $conn->error;
    } else {
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();

        if (!$item) {
            $error_msg = "Item not found or not available.";
        } else {
            $seller_name  = $item['username'] ?? '';
            $seller_photo = $item['profile_photo'] ?? '';
        }
    }
}

// Telegram-style initials for seller (fallback)
$seller_initials = 'U';
if (!empty($seller_name)) {
    $parts = preg_split('/\s+/', trim($seller_name));
    $first = strtoupper(mb_substr($parts[0], 0, 1));
    $second = count($parts) > 1 ? strtoupper(mb_substr($parts[1], 0, 1)) : '';
    $seller_initials = $first . $second;
}

// Load multiple images from item_images table (your DB has item_images)
$images = [];
if ($item) {
    $stmt = $conn->prepare("SELECT image_path FROM item_images WHERE item_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            if (!empty($r['image_path'])) {
                $images[] = $r['image_path'];
            }
        }
        $stmt->close();
    }
    // fallback: main image
    if (empty($images) && !empty($item['image'])) {
        $images[] = $item['image'];
    }
}

// Check if already in cart (for initial state)
$already_in_cart = false;
if ($logged_in && $item) {
    $stmt = $conn->prepare("SELECT 1 FROM cart_items WHERE user_id = ? AND item_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ii", $user_id, $item_id);
        $stmt->execute();
        $stmt->store_result();
        $already_in_cart = $stmt->num_rows > 0;
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Item Detail</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <!-- your existing home styles -->
  <link rel="stylesheet" href="/Ecommerce/User/css/home.css">
  <!-- new detail styles (seller bar + carousel) -->
  <link rel="stylesheet" href="/Ecommerce/User/css/items-detail.css">
</head>
<body>

  <!-- top nav (reuse) -->
  <div class="topnav" id="myTopnav">
    <a href="home.php" class="logo">
      <img src="../assets/img/logo.png" alt="logo">
    </a>

    <a href="javascript:void(0);" class="icon" onclick="toggleNav()">
      <i class="fa fa-bars"></i>
    </a>

    <form role="search" method="get" action="search.php" class="search">
      <input type="search" name="q" placeholder="Search products">
    </form>

    <div class="buttons"><a href="home.php">Home</a></div>
    <div class="buttons"><a href="post.php">Post</a></div>
    <div class="buttons"><a href="profile.php">Profile</a></div>
    <div class="buttons"><a href="cart.php">Carts</a></div>

    <?php if ($logged_in): ?>
      <div class="btn-logout">
        <a href="index.php" class="btn-logout">Logout</a>
      </div>
    <?php endif; ?>
  </div>

  <section class="content">
    <?php if (!empty($error_msg)): ?>
      <div class="alert error"><?= htmlspecialchars($error_msg) ?></div>
      <a href="home.php" class="btn secondary" style="margin-top:10px; display:inline-block;"> Back</a>

    <?php elseif ($item): ?>

      <div class="detail-wrap">

        <!-- LEFT: carousel images -->
        <div class="detail-images">
          <?php if (!empty($images)): ?>
            <div class="carousel">
              <button class="carousel-btn prev" onclick="prevSlide()">&#10094;</button>
              <button class="carousel-btn next" onclick="nextSlide()">&#10095;</button>

              <?php foreach ($images as $index => $img): ?>
                <img
                  class="carousel-img <?= $index === 0 ? 'active' : '' ?>"
                  src="<?= htmlspecialchars($img) ?>"
                  alt="<?= htmlspecialchars($item['title']) ?>"
                >
              <?php endforeach; ?>
            </div>

            <div class="carousel-dots">
              <?php foreach ($images as $index => $img): ?>
                <span
                  class="dot <?= $index === 0 ? 'active-dot' : '' ?>"
                  onclick="goSlide(<?= $index ?>)">
                </span>
              <?php endforeach; ?>
            </div>

          <?php else: ?>
            <div class="placeholder-img">No image</div>
          <?php endif; ?>
        </div>

        <!-- RIGHT: info -->
        <div class="detail-info">

          <!-- Seller info bar -->
          <div class="seller-bar">
            <div class="seller-avatar">
              <?php if (!empty($seller_photo)): ?>
                <img src="<?= htmlspecialchars($seller_photo) ?>" alt="seller">
              <?php else: ?>
                <span><?= htmlspecialchars($seller_initials) ?></span>
              <?php endif; ?>
            </div>
            <div class="seller-name">
              <small>Seller</small>
              <div><?= htmlspecialchars($seller_name ?: 'Unknown') ?></div>
            </div>
          </div>

          <h1 class="detail-title"><?= htmlspecialchars($item['title']) ?></h1>
          <div class="detail-price"><?= number_format($item['price'], 0) ?> MMK</div>

          <div class="detail-meta">
            <?php if (!empty($item['category_label'])): ?>
              <p><strong>Category:</strong> <?= htmlspecialchars($item['category_label']) ?></p>
            <?php endif; ?>
            <?php if (!empty($item['item_condition'])): ?>
              <p><strong>Condition:</strong> <?= htmlspecialchars($item['item_condition']) ?></p>
            <?php endif; ?>
            <?php if (!empty($item['location'])): ?>
              <p><strong>Location:</strong> <?= htmlspecialchars($item['location']) ?></p>
            <?php endif; ?>
            <?php if (isset($item['stock'])): ?>
              <p><strong>Stock:</strong> <?= (int)$item['stock'] ?></p>
            <?php endif; ?>
          </div>

          <div class="detail-desc">
            <strong>Description</strong><br>
            <?= !empty($item['description'])
                ? htmlspecialchars($item['description'])
                : "No description provided."; ?>
          </div>

          <!-- actions -->
          <div class="detail-actions">
            <?php if ($is_admin_view): ?>
              <a href="/Ecommerce/admin/admin_home.php" class="btn secondary">Back to Admin Dashboard</a>

            <?php elseif ($logged_in && (int)$item['seller_id'] !== $user_id): ?>

              <!-- Buy now = single item checkout -->
              <a href="checkout.php?buy_now=1&item_id=<?= (int)$item['id'] ?>"
                 class="btn primary">
                Buy now
              </a>

              <!-- Add to cart AJAX -->
              <?php if (!$already_in_cart): ?>
                <button type="button"
                        id="addToCartBtn"
                        class="btn secondary"
                        data-item-id="<?= (int)$item['id'] ?>">
                  Add to cart
                </button>
                <span id="inCartLabel" class="btn secondary in-cart-label">
                  In cart
                </span>
              <?php else: ?>
                <span class="btn secondary in-cart-label show">
                  In cart
                </span>
              <?php endif; ?>

              <a href="home.php" class="btn secondary">Back to Home</a>

            <?php elseif ($logged_in && (int)$item['seller_id'] === $user_id): ?>
              <a href="edit_item.php?id=<?= (int)$item['id'] ?>" class="btn secondary">Edit this item</a>
              <a href="home.php" class="btn secondary">Back to Home</a>

            <?php else: ?>
              <a href="login.php" class="btn primary">Login to buy</a>
              <a href="home.php" class="btn secondary">Back to Home</a>
            <?php endif; ?>
          </div>

        </div>
      </div>

    <?php endif; ?>
  </section>

  <script>
    function toggleNav() {
      var x = document.getElementById("myTopnav");
      if (x.className === "topnav") {
        x.className += " responsive";
      } else {
        x.className = "topnav";
      }
    }

    // Carousel JS
    let currentSlide = 0;

    function showSlide(index) {
      const slides = document.querySelectorAll(".carousel-img");
      const dots = document.querySelectorAll(".dot");
      if (!slides.length) return;

      slides.forEach(s => s.classList.remove("active"));
      dots.forEach(d => d.classList.remove("active-dot"));

      currentSlide = (index + slides.length) % slides.length;
      slides[currentSlide].classList.add("active");
      if (dots[currentSlide]) dots[currentSlide].classList.add("active-dot");
    }

    function nextSlide() { showSlide(currentSlide + 1); }
    function prevSlide() { showSlide(currentSlide - 1); }
    function goSlide(i) { showSlide(i); }

    document.addEventListener("DOMContentLoaded", () => {
      showSlide(0);

      // click on image to go next
      const slides = document.querySelectorAll(".carousel-img");
      slides.forEach(slide => {
        slide.style.cursor = "pointer";
        slide.addEventListener("click", nextSlide);
      });
    });

    // AJAX Add to cart (same endpoint as home.php)
    document.addEventListener("DOMContentLoaded", function () {
      const btn = document.getElementById("addToCartBtn");
      if (!btn) return;

      btn.addEventListener("click", async function () {
        const itemId = this.dataset.itemId;

        this.disabled = true;
        this.style.opacity = "0.6";

        try {
          const fd = new FormData();
          fd.append("item_id", itemId);

          const res = await fetch("add_to_cart_ajax.php", {
            method: "POST",
            body: fd
          });

          const data = await res.json();

          if (!data.ok) {
            if (data.message === "not_logged_in") {
              window.location.href = "/login.php";
              return;
            }
            alert(data.message.replaceAll("_"," "));
            this.disabled = false;
            this.style.opacity = "";
            return;
          }

          // success -> hide button, show label
          this.style.display = "none";
          const label = document.getElementById("inCartLabel");
          if (label) label.classList.add("show");

        } catch (e) {
          alert("Network error. Try again.");
          this.disabled = false;
          this.style.opacity = "";
        }
      });
    });
  </script>
</body>
</html>
