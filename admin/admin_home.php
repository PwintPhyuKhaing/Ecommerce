<?php
// admin_home.php – Admin dashboard: manage all items + all users

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';   // session + $conn + is_admin()

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: /Ecommerce/User/login.php");
    exit;
}
if (!is_admin()) {
    header("Location: /Ecommerce/User/access-denied.php");
    exit;
}

$admin_id = (int)($_SESSION['user_id'] ?? 0);

// navbar filters
$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

// load all active items (with search + category)
$items = [];
// We build query based on which filters are present.
// NOTE: we keep it simple with 4 cases to avoid dynamic placeholders.
if ($search !== '' && $category !== '') {
    $stmt = $conn->prepare("
        SELECT i.*, u.username AS seller_name
        FROM items i
        JOIN users u ON u.id = i.seller_id
        WHERE i.status='active'
          AND i.category_label = ?
          AND (i.title LIKE CONCAT('%', ?, '%')
               OR i.description LIKE CONCAT('%', ?, '%'))
        ORDER BY i.created_at DESC
    ");
    if ($stmt) $stmt->bind_param("sss", $category, $search, $search);

} elseif ($search !== '') {
    $stmt = $conn->prepare("
        SELECT i.*, u.username AS seller_name
        FROM items i
        JOIN users u ON u.id = i.seller_id
        WHERE i.status='active'
          AND (i.title LIKE CONCAT('%', ?, '%')
               OR i.description LIKE CONCAT('%', ?, '%'))
        ORDER BY i.created_at DESC
    ");
    if ($stmt) $stmt->bind_param("ss", $search, $search);

} elseif ($category !== '') {
    $stmt = $conn->prepare("
        SELECT i.*, u.username AS seller_name
        FROM items i
        JOIN users u ON u.id = i.seller_id
        WHERE i.status='active'
          AND i.category_label = ?
        ORDER BY i.created_at DESC
    ");
    if ($stmt) $stmt->bind_param("s", $category);

} else {
    $stmt = $conn->prepare("
        SELECT i.*, u.username AS seller_name
        FROM items i
        JOIN users u ON u.id = i.seller_id
        WHERE i.status='active'
        ORDER BY i.created_at DESC
    ");
}

if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $items[] = $row;
    $stmt->close();
}

// load all users
$users = [];
$u_stmt = $conn->prepare("
    SELECT id, username, email, role, created_at
    FROM users
    ORDER BY created_at DESC
");
if ($u_stmt) {
    $u_stmt->execute();
    $u_res = $u_stmt->get_result();
    while ($row = $u_res->fetch_assoc()) $users[] = $row;
    $u_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Home</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <!-- ✅ reuse same css as user home -->
  <link rel="stylesheet" href="/Ecommerce/User/css/home.css">
  <style>
    /* small admin-only tweaks (keep it minimal; main layout comes from User/css/home.css) */
    .admin-badge{
      background:#facc15;
      color:#111827;
      padding:6px 10px;
      border-radius:999px;
      font-size:12px;
      font-weight:700;
      margin-left:auto;
    }

    /* users table */
    .table-wrap{width:100%; overflow-x:auto; margin-top:12px;}
    .user-table{width:100%; border-collapse:collapse; background:#ffffff; border-radius:12px; overflow:hidden; min-width:720px;}
    .user-table th,.user-table td{padding:10px; border-bottom:1px solid #eee; text-align:left; font-size:14px;}
    .user-table th{background:#111827; color:#fff; position:sticky; top:0;}
    .danger-btn{background:#dc2626;color:#fff;border:none;padding:6px 10px;border-radius:8px;cursor:pointer;}
    .danger-btn:hover{opacity:.9;}

    /* admin action buttons on cards */
    .owner-actions .btn.secondary{background-color:#3b82f6;color:#fff;}
    .owner-actions .btn.secondary:hover{background-color:#2563eb;}
    /* Detail button (purple) */
    .owner-actions .btn.p{background-color:#b53ad7;color:#fff;}
    .owner-actions .btn.p:hover{opacity:.92;}
    .owner-actions .btn.danger{background-color:#dc2626;color:#fff;}
    .owner-actions .btn.danger:hover{background-color:#b91c1c;}

    /* topnav: make room for badge on small screens */
    @media (max-width: 768px){
      .admin-badge{margin-left:0;}
    }
  </style>
</head>
<body>

  <!-- ✅ Topnav UI same as User/home.php (category + search + responsive) -->
  <div class="topnav" id="myTopnav">
    <a href="/Ecommerce/admin/admin_home.php" class="logo">
      <img src="../assets/img/logo.png" alt="logo">
    </a>

    <a href="javascript:void(0);" class="icon" onclick="toggleNav()">
      <i class="fa fa-bars"></i>
    </a>

    <!-- ✅ Category select (same as user home.php) -->
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

    <!-- ✅ Search bar style same as user home.php -->
    <form role="search" method="get" action="admin_home.php" class="search">
      <input type="search" name="search" placeholder="Search products" value="<?= htmlspecialchars($search) ?>">
      <?php if ($category !== ''): ?>
        <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
      <?php endif; ?>
    </form>

    <div class="buttons">
      <a href="admin_home.php" onclick="showTab('homeTab'); return false;">Home</a>
    </div>

    <div class="buttons">
      <a href="#" onclick="showTab('usersTab'); return false;">All users</a>
    </div>

    <div class="btn-logout">
      <a href="/Ecommerce/user/index.php" class="btn-logout">Logout</a>
    </div>

    <span class="admin-badge">ADMIN</span>
  </div>

  <!-- ✅ Same content wrapper as user home.php -->
  <section class="content">
    <h2>ADMIN DASHBOARD</h2>

    <!-- ✅ Home tab = All posts (admin manage) -->
    <div id="homeTab">
      <?php if (!empty($category) || !empty($search)): ?>
        <p style="margin:10px 0;">
          Filter:
          <?php if (!empty($category)): ?>
            <strong><?= htmlspecialchars($category) ?></strong>
          <?php endif; ?>
          <?php if (!empty($search)): ?>
            <span style="margin-left:6px;">Search: <strong><?= htmlspecialchars($search) ?></strong></span>
          <?php endif; ?>
          <a href="admin_home.php" style="margin-left:8px;">(Clear)</a>
        </p>
      <?php endif; ?>

      <?php if (empty($items)): ?>
        <p>No items found.</p>
      <?php else: ?>
        <div class="posts-grid">
          <?php foreach ($items as $item): ?>
            <article class="post-card">
              <div class="post-image">
                <?php if (!empty($item['image'])): ?>
                  <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
                <?php else: ?>
                  <div class="placeholder-img">No image</div>
                <?php endif; ?>
              </div>

              <div class="post-body">
                <h3><?= htmlspecialchars($item['title']) ?></h3>
                <p class="price"><?= number_format((float)$item['price'], 0) ?> MMK</p>

                <?php if (!empty($item['category_label'])): ?>
                  <p class="meta"><strong>Category:</strong> <?= htmlspecialchars($item['category_label']) ?></p>
                <?php endif; ?>
                <?php if (!empty($item['item_condition'])): ?>
                  <p class="meta"><strong>Condition:</strong> <?= htmlspecialchars($item['item_condition']) ?></p>
                <?php endif; ?>
                <?php if (!empty($item['location'])): ?>
                  <p class="meta"><strong>Location:</strong> <?= htmlspecialchars($item['location']) ?></p>
                <?php endif; ?>
                <p class="meta"><strong>Seller:</strong> <?= htmlspecialchars($item['seller_name']) ?></p>

                <?php if (!empty($item['description'])): ?>
                  <p class="meta" style="margin-top:8px; color:#374151;">
                    <?= htmlspecialchars($item['description']) ?>
                  </p>
                <?php endif; ?>

                <div class="owner-actions">
                  <a href="/Ecommerce/User/items-detail.php?item_id=<?= (int)$item['id'] ?>" class="btn p">Detail</a>
                  <a href="admin_edit_item.php?id=<?= (int)$item['id'] ?>" class="btn secondary">Edit</a>

                  <form method="POST" action="admin_delete_item.php" class="inline-form"
                        onsubmit="return confirm('Delete this post? This cannot be undone.');">
                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                    <button type="submit" class="btn danger">Delete</button>
                  </form>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ✅ Users tab -->
    <div id="usersTab" style="display:none;">
      <h2 style="font-size:1.5rem; margin-top:30px;">All Users</h2>
      <div class="table-wrap">
        <table class="user-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Username</th>
              <th>Email</th>
              <th>Role</th>
              <th>Created</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($users as $i => $u): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><?= htmlspecialchars($u['username']) ?></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><?= htmlspecialchars($u['role']) ?></td>
              <td><?= htmlspecialchars($u['created_at']) ?></td>
              <td>
                <?php if ((int)$u['id'] === $admin_id): ?>
                  <small>(you)</small>
                <?php else: ?>
                  <form method="POST" action="admin_delete_user.php" onsubmit="return confirm('Delete this user account?');">
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <button class="danger-btn" type="submit">Delete</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </section>

  <script>
    function toggleNav() {
      var x = document.getElementById("myTopnav");
      if (x.className === "topnav") x.className += " responsive";
      else x.className = "topnav";
    }

    function showTab(tabId){
      document.getElementById('homeTab').style.display = (tabId==='homeTab') ? 'block' : 'none';
      document.getElementById('usersTab').style.display = (tabId==='usersTab') ? 'block' : 'none';
    }

    // ✅ Category change -> keep same admin_home.php filter UX as user home.php
    document.addEventListener('DOMContentLoaded', function(){
      const catSelect = document.getElementById('categorySelect');
      if (catSelect) {
        catSelect.addEventListener('change', function(){
          const val = this.value;
          const params = new URLSearchParams(window.location.search);
          if (val) params.set('category', val); else params.delete('category');
          // keep current search keyword if any
          window.location.href = 'admin_home.php' + (params.toString() ? ('?' + params.toString()) : '');
        });
      }
    });
  </script>
</body>
</html>
