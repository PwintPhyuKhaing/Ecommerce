<?php
// index.php – public homepage (no login required)
require_once __DIR__ . '/../admin/config.php';

/* ✅ Filters */
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$q        = isset($_GET['q']) ? trim($_GET['q']) : '';

/* ✅ LOAD ITEMS with category + search filter */
$items = [];

$sql = "
    SELECT id, title, price, image, category_label, item_condition, location, created_at
    FROM items
    WHERE status='active'
";

$params = [];
$types  = "";

if ($category !== '') {
    $sql .= " AND category_label = ?";
    $params[] = $category;
    $types .= "s";
}

if ($q !== '') {
    $sql .= " AND (title LIKE CONCAT('%', ?, '%')
              OR category_label LIKE CONCAT('%', ?, '%')
              OR location LIKE CONCAT('%', ?, '%'))";
    $params[] = $q;
    $params[] = $q;
    $params[] = $q;
    $types .= "sss";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $items = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>All Posts</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="/Ecommerce/User/css/index.css">
  <link rel="stylesheet" href="/Ecommerce/User/css/home.css">
</head>
<body>

  <div class="topnav" id="myTopnav">
    <a href="index.php" class="logo">
      <img src="../assets/img/logo.png" alt="logo"> 
    </a>

    <a href="javascript:void(0);" class="icon" onclick="toggleNav()">
      <i class="fa fa-bars"></i>
    </a>

    <!-- ✅ Category select -->
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

    <!-- ✅ Search stays on index.php -->
    <form role="search" method="get" action="index.php" class="search">
      <input type="search" name="q" placeholder="Search products"
             value="<?= htmlspecialchars($q) ?>">
    </form>

    <div class="buttons"><a href="index.php" class="home">Home</a></div>
    <div class="buttons"><a href="login.php" class="buy">Buy</a></div>
    <div class="buttons"><a href="login.php" class="sell">Sell</a></div>
    <div class="buttons"><a href="login.php" class="btn-login">Login</a></div>
  </div>

  <section class="content">
    <h2>SECOND HAND PRODUCTS MARKET</h2>

    <?php if (!empty($category)): ?>
      <p style="margin-bottom:10px;">
        Showing category: <strong><?= htmlspecialchars($category) ?></strong>
        <a href="index.php<?= $q ? '?q='.urlencode($q) : '' ?>" style="margin-left:8px;">(Clear)</a>
      </p>
    <?php endif; ?>

    <?php if (!empty($q)): ?>
      <p style="margin-bottom:10px;">
        Showing search: <strong><?= htmlspecialchars($q) ?></strong>
        <a href="index.php<?= $category ? '?category='.urlencode($category) : '' ?>" style="margin-left:8px;">(Clear)</a>
      </p>
    <?php endif; ?>

    <?php if (empty($items)): ?>
      <p>No items posted yet.</p>
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

              <a href="login.php" class="btn primary" style="margin-top:10px;display:inline-block;">
                Login to Buy
              </a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <script>
    function toggleNav() {
      var x = document.getElementById("myTopnav");
      if (x.className === "topnav") x.className += " responsive";
      else x.className = "topnav";
    }

    // ✅ Category change -> preserve search q
    document.addEventListener("DOMContentLoaded", function () {
      const catSelect = document.getElementById("categorySelect");
      if (catSelect) {
        catSelect.addEventListener("change", function () {
          const params = new URLSearchParams(window.location.search);
          if (this.value) params.set("category", this.value);
          else params.delete("category");
          window.location.href = "index.php" + (params.toString() ? "?" + params.toString() : "");
        });
      }
    });
  </script>

</body>
</html>
