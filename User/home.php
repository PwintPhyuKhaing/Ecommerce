<?php
// home.php – show all active items + allow add to cart (AJAX) / buy now (single item checkout)

// Show errors while developing (optional)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// central config: session + DB
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/auth.php';

$logged_in = isset($_SESSION['user_id']);
$user_id   = $logged_in ? (int)$_SESSION['user_id'] : null;

$info_msg  = '';
$error_msg = '';

/*
  NOTE:
  - Buy now is handled by normal POST submit and redirects to checkout.php?buy_now=1&item_id=XX
  - Add to cart is handled via AJAX (add_to_cart_ajax.php). So we DO NOT process add_to_cart POST here.
*/

// HANDLE "BUY NOW" only (single-item checkout)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['buy_now'])
) {
    if (!$logged_in) {
        header('Location: /login.php');
        exit;
    }

    $item_id  = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;

    // Validate item
    $stmt = $conn->prepare("SELECT id, stock, status FROM items WHERE id = ? AND status = 'active'");
    if (!$stmt) {
        $error_msg = "Database error: " . $conn->error;
    } else {
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $error_msg = "That item is not available.";
            $stmt->close();
        } else {
            $stmt->bind_result($db_item_id, $stock, $status);
            $stmt->fetch();
            $stmt->close();

            if ($stock <= 0) {
                $error_msg = "That item is out of stock.";
            } else {
                // ✅ BUY NOW → do NOT touch cart, go single-item checkout
                header("Location: checkout.php?buy_now=1&item_id=$item_id");
                exit;
            }
        }
    }
}

/* ✅ Preload cart item ids for this user (to hide Add To Cart btn on initial render) */
$cart_item_ids = [];
if ($logged_in) {
    $stmt = $conn->prepare("SELECT item_id FROM cart_items WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $cart_item_ids[(int)$r['item_id']] = true; // set-like array
        }
        $stmt->close();
    }
}

/* ✅ Category filter (from navbar select -> home.php?category=XXX) */
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

/* LOAD ITEMS (filter by category if provided) */
$items = [];

if ($category !== '') {
    $stmt = $conn->prepare("
        SELECT id, seller_id, title, price, image, category_label, item_condition, location, created_at
        FROM items
        WHERE status='active' AND category_label = ?
        ORDER BY created_at DESC
    ");
    if ($stmt) {
        $stmt->bind_param("s", $category);
        $stmt->execute();
        $res = $stmt->get_result();
        $items = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $error_msg = "Could not load items: " . $conn->error;
    }
} else {
    $sql = "
        SELECT id, seller_id, title, price, image, category_label, item_condition, location, created_at 
        FROM items
        WHERE status = 'active'
        ORDER BY created_at DESC
    ";
    $result = $conn->query($sql);
    if ($result) {
        $items = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
    } else {
        $error_msg = "Could not load items: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>All Posts</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="/Ecommerce/User/css/home.css">
</head>
<body>
  <div class="topnav" id="myTopnav">
    <a href="home.php" class="logo">
      <img src="../assets/img/logo.png" alt="logo">
    </a>

    <a href="javascript:void(0);" class="icon" onclick="toggleNav()">
      <i class="fa fa-bars"></i>
    </a>

    <!-- ✅ Category select now works -->
    <div class="category">
      <select id="categorySelect" name="category">
        <option value="">Category</option>
        <option value="Clothes"      <?= $category==='Clothes' ? 'selected':''; ?>>Clothes</option>
        <option value="Shoes"        <?= $category==='Shoes' ? 'selected':''; ?>>Shoes</option>
        <option value="Clocks"       <?= $category==='Clocks' ? 'selected':''; ?>>Clocks</option>
        <option value="Books"        <?= $category==='Books' ? 'selected':''; ?>>Books</option>
        <option value="Toys"         <?= $category==='Toys' ? 'selected':''; ?>>Toys</option>
        <option value="Electronics"  <?= $category==='Electronics' ? 'selected':''; ?>>Electronics</option>
        <option value="Cosmetics"    <?= $category==='Cosmetics' ? 'selected':''; ?>>Cosmetics</option>
        <option value="Appliances"   <?= $category==='Appliances' ? 'selected':''; ?>>Appliances</option>
        <option value="Accessories"  <?= $category==='Accessories' ? 'selected':''; ?>>Accessories</option>
        <option value="Furniture"    <?= $category==='Furniture' ? 'selected':''; ?>>Furniture</option>
        <option value="Kitchenware"  <?= $category==='Kitchenware' ? 'selected':''; ?>>Kitchenware</option>
        <option value="Machines"     <?= $category==='Machines' ? 'selected':''; ?>>Machines</option>
      </select>
    </div>

    <!-- ✅ Search form (use absolute path if your search.php is under /Ecommerce/User/) -->
    <form role="search" method="get" action="/Ecommerce/User/search.php" class="search">
      <input type="search" name="q" placeholder="Search products">
    </form>

    <div class="buttons">
      <a href="home.php">Home</a>
    </div>

    <div class="buttons">
      <a href="post.php">Post</a>
    </div>

    <div class="buttons">
      <a href="profile.php">Profile</a>
    </div>

    <div class="buttons">
      <a href="cart.php">Carts</a>
    </div>

    <?php if ($logged_in): ?>
      <div class="btn-logout">
        <a href="index.php" class="btn-logout">Logout</a>
      </div>
    <?php endif; ?>
  </div>

  <section class="content">
    <h2>SECOND HAND PRODUCTS MARKET</h2>

    <?php if (!empty($category)): ?>
      <p style="margin-bottom:10px;">
        Showing category: <strong><?= htmlspecialchars($category) ?></strong>
        <a href="home.php" style="margin-left:8px;">(Clear)</a>
      </p>
    <?php endif; ?>

    <?php if (!empty($info_msg)): ?>
      <div class="alert success"><?= htmlspecialchars($info_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
      <div class="alert error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <?php if (empty($items)): ?>
      <p>No items posted yet. Be the first to <a href="post.php">post a product →</a></p>
    <?php else: ?>
      <div class="posts-grid">
        <?php foreach ($items as $item): ?>
          <article class="post-card">
            <div class="post-image">
              <?php if (!empty($item['image'])): ?>
                <img src="<?= htmlspecialchars($item['image']) ?>"
                     alt="<?= htmlspecialchars($item['title']) ?>">
              <?php else: ?>
                <div class="placeholder-img">No image</div>
              <?php endif; ?>
            </div>

            <div class="post-body">
              <h3><?= htmlspecialchars($item['title']) ?></h3>
              <p class="price"><?= number_format($item['price'], 0) ?> MMK</p>

              <?php if (!empty($item['category_label'])): ?>
                <p class="meta"><strong>Category:</strong> <?= htmlspecialchars($item['category_label']) ?></p>
              <?php endif; ?>
              <?php if (!empty($item['item_condition'])): ?>
                <p class="meta"><strong>Condition:</strong> <?= htmlspecialchars($item['item_condition']) ?></p>
              <?php endif; ?>
              <?php if (!empty($item['location'])): ?>
                <p class="meta"><strong>Location:</strong> <?= htmlspecialchars($item['location']) ?></p>
              <?php endif; ?>

              <?php if ($logged_in && (int)$item['seller_id'] === $user_id): ?>
                <!-- YOUR OWN POST → Edit/Delete -->
                <div class="owner-actions">
                  <a href="edit_item.php?id=<?= (int)$item['id'] ?>" class="btn secondary">
                    Edit
                  </a>
                  <form method="post" action="my_listings.php" class="inline-form"
                        onsubmit="return confirm('Delete this item? This cannot be undone.');">
                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                    <button type="submit" name="delete_item" class="btn danger">
                      Delete
                    </button>
                  </form>
                </div>

              <?php else: ?>
                <!-- OTHER PEOPLE'S POSTS → Buy now (POST) / Add to cart (AJAX) -->
                <?php
                  $already_in_cart = $logged_in && isset($cart_item_ids[(int)$item['id']]);
                ?>

                <form method="post" class="add-cart-form">
                  <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">

                  <button type="submit" name="buy_now" class="btn primary">
                    Buy now
                  </button>

                  <?php if (!$already_in_cart): ?>
                    <!--  AJAX add to cart button -->
                    <button type="button"
                            class="btn p add-to-cart-btn"
                            data-item-id="<?= (int)$item['id'] ?>">
                      Add to cart
                    </button>

                    <!-- hidden label shown after AJAX success -->
                    <span class="btn secondary in-cart-label"
                          style="display:none; opacity:0.6; cursor:default;">
                      In cart
                    </span>
                  <?php else: ?>
                    <span class="btn secondary in-cart-label"
                          style="display:inline-block; opacity:0.6; cursor:default;">
                      In cart
                    </span>
                  <?php endif; ?>
                </form>

                <!-- Detail outside form -->
                <a href="items-detail.php?item_id=<?= (int)$item['id'] ?>"
                   class="btn gg"
                   style="display:inline-block; margin-top:8px;">
                  Detail
                </a>

              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
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

    document.addEventListener("DOMContentLoaded", function () {
      // ✅ AJAX add-to-cart (hide button instantly without refresh)
      const buttons = document.querySelectorAll(".add-to-cart-btn");

      buttons.forEach(btn => {
        btn.addEventListener("click", async function () {
          const itemId = this.dataset.itemId;

          this.disabled = true;
          this.style.opacity = "0.6";

          try {
            const formData = new FormData();
            formData.append("item_id", itemId);

            const res = await fetch("add_to_cart_ajax.php", {
              method: "POST",
              body: formData
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

            // success -> hide Add to cart, show In cart
            const form = this.closest(".add-cart-form");
            this.style.display = "none";

            const label = form.querySelector(".in-cart-label");
            if (label) label.style.display = "inline-block";

          } catch (err) {
            alert("Network error. Try again.");
            this.disabled = false;
            this.style.opacity = "";
          }
        });
      });

      // ✅ Category change -> redirect with filter
      const catSelect = document.getElementById("categorySelect");
      if (catSelect) {
        catSelect.addEventListener("change", function () {
          const val = this.value;
          window.location.href = val
            ? `home.php?category=${encodeURIComponent(val)}`
            : "home.php";
        });
      }
    });
  </script>
</body>
</html>
