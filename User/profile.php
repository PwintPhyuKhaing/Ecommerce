<?php
require __DIR__ . '/../admin/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// ✅ Always upload inside YOUR project folder (safe on Windows/Linux)
$upload_dir = realpath(__DIR__ . "/..") . "/uploads/profiles/";

// ✅ use the same folder name as your actual project URL
$upload_url = "/ecommerce/uploads/profiles/";  // <-- if your project URL is /ecommerce/


if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ✅ Get current user info
$stmt = $conn->prepare("
    SELECT username, email, full_name, phone, address, profile_photo
    FROM users WHERE id=?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username, $email, $full_name, $phone, $address, $current_photo);
$stmt->fetch();
$stmt->close();


// HANDLE PROFILE UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {

    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');

    $new_photo_path = null;

    // ✅ Stronger "file exists" check
    if (isset($_FILES['profile_photo']) && !empty($_FILES['profile_photo']['name'])) {

        $file = $_FILES['profile_photo'];

        if ($file['error'] === UPLOAD_ERR_OK) {

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];

            if (!in_array($ext, $allowed)) {
                $error_msg = "Only JPG, PNG, GIF, WEBP files allowed.";
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error_msg = "File too large. Max 5MB.";
            } elseif (!is_uploaded_file($file['tmp_name'])) {
                $error_msg = "Upload temp file missing.";
            } else {

                $filename = "user_" . $user_id . "_" . time() . "." . $ext;
                $target_path = $upload_dir . $filename;

                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $new_photo_path = $upload_url . $filename;
                } else {
                    $error_msg = "Photo move failed. Target: " . $target_path;
                }
            }

        } else {
            // ✅ show real upload error
            $error_msg = "Upload error code: " . $file['error'] .
                         " (check php.ini upload_max_filesize, post_max_size)";
        }

    } else {
        // no file selected is OK -> will just update text fields
        $new_photo_path = null;
    }

    if ($error_msg === '') {

        if ($new_photo_path) {
            $stmt = $conn->prepare("
                UPDATE users
                SET full_name=?, username=?, email=?, phone=?, address=?, profile_photo=?
                WHERE id=?
            ");
            $stmt->bind_param(
                "ssssssi",
                $full_name, $username, $email, $phone, $address, $new_photo_path, $user_id
            );
        } else {
            $stmt = $conn->prepare("
                UPDATE users
                SET full_name=?, username=?, email=?, phone=?, address=?
                WHERE id=?
            ");
            $stmt->bind_param(
                "sssssi",
                $full_name, $username, $email, $phone, $address, $user_id
            );
        }

        if ($stmt->execute()) {
            $success_msg = "Profile updated!";
            if ($new_photo_path) {
                $current_photo = $new_photo_path; // ✅ instant update
            }
        } else {
            $error_msg = "DB update failed: " . $stmt->error;
        }
        $stmt->close();

        // re-fetch
        $stmt = $conn->prepare("
            SELECT username, email, full_name, phone, address, profile_photo
            FROM users WHERE id=?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($username, $email, $full_name, $phone, $address, $current_photo);
        $stmt->fetch();
        $stmt->close();
    }
}


// initials
$display_name = $full_name ?: $username;
$parts = preg_split('/\s+/', trim($display_name));
$initials = strtoupper(mb_substr($parts[0] ?? 'U', 0, 1)) .
            strtoupper(mb_substr($parts[1] ?? '', 0, 1));

// stats
$stmt = $conn->prepare("SELECT COUNT(*) FROM items WHERE seller_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($total_listings);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($total_purchases);
$stmt->fetch();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>

    <link rel="stylesheet" href="/Ecommerce/User/css/home.css">
    <link rel="stylesheet" href="/Ecommerce/User/css/user-dashboard.css">

    <!-- camera icon -->
    <link rel="stylesheet"
     href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        .profile-photo-wrap{
            display:flex; gap:16px; align-items:center; margin-bottom:18px;
        }
        .avatar{
            width:90px; height:90px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-size:30px; font-weight:700; color:#fff;
            background:#5b7cfa;
            overflow:hidden; cursor:pointer; position:relative;
            border:2px solid #ddd; user-select:none;
        }
        .avatar img{ width:100%; height:100%; object-fit:cover; display:block; }

        .avatar .edit-badge{
            position:absolute; bottom:3px; right:3px;
            width:28px; height:28px; border-radius:50%;
            background:#000b; color:#fff; font-size:13px;
            display:flex; align-items:center; justify-content:center;
            border:2px solid #fff;
        }
        .avatar:hover .edit-badge{ background:#5b7cfa; }

        .photo-input{ display:none; }
        .avatar-hint{ font-size:12px; color:#666; margin-top:4px; }

        .topnav{
            position: fixed; top: 0; left: 0; right: 0; z-index: 9999;
        }
        body{ padding-top: 75px; }

        /* ✅ Delete Account styles */
        .danger-zone{
            margin-top:22px;
            padding:16px;
            border:1px solid #f5b7b1;
            background:#fff6f6;
            border-radius:10px;
        }
        .danger-zone h3{
            margin:0 0 6px 0;
            color:#c0392b;
        }
        .danger-zone p{
            font-size:13px;
            color:#555;
            margin-bottom:12px;
        }
        .btn-danger{
            background:#e74c3c;
            color:white;
            padding:10px 14px;
            border-radius:8px;
            text-decoration:none;
            display:inline-block;
            font-weight:600;
        }
        .btn-danger:hover{ opacity:0.9; }
    </style>
</head>
<body>

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
    <div class="buttons"><a href="my_listings.php">My Listings</a></div>
    <div class="buttons"><a href="purchases.php">Purchases</a></div>
    <div class="buttons"><a href="cart.php">Cart</a></div>
    <div class="btn-logout">
        <a href="/Ecommerce/assets/logout.php" class="btn-logout">Logout</a>
    </div>
</div>

<main class="page">
    <h1>My Profile</h1>

    <?php if ($success_msg): ?>
        <div class="alert success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <section class="grid-2">
        <div class="card">
            <h2>Account details</h2>

            <form method="post" action="profile.php" class="form" enctype="multipart/form-data">

                <div class="profile-photo-wrap">
                    <div class="avatar" id="avatarBox" title="Click to change photo">
                        <?php if (!empty($current_photo)): ?>
                            <img id="avatarImg"
                                 src="<?= htmlspecialchars($current_photo) ?>?v=<?= time() ?>"
                                 alt="Profile photo">
                        <?php else: ?>
                            <span id="avatarText"><?= htmlspecialchars($initials) ?></span>
                        <?php endif; ?>
                        <div class="edit-badge"><i class="fa-solid fa-camera"></i></div>
                    </div>

                    <input type="file" id="profile_photo" name="profile_photo"
                           accept="image/*" class="photo-input">

                    <div>
                        <strong>Profile photo</strong>
                        <div class="avatar-hint">Click the circle to upload/change</div>
                        <div class="avatar-hint">JPG / PNG / GIF / WEBP only (max 5MB)</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="full_name">Full name</label>
                    <input type="text" id="full_name" name="full_name"
                           value="<?= htmlspecialchars($full_name) ?>" required>
                </div>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username"
                           value="<?= htmlspecialchars($username) ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($email) ?>" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone"
                           value="<?= htmlspecialchars($phone) ?>">
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3"><?= htmlspecialchars($address) ?></textarea>
                </div>

                <button type="submit" name="update_profile" class="btn-primary">
                    Save changes
                </button>
            </form>

            <!-- ✅ DANGER ZONE: Delete account -->
            <div class="danger-zone">
                <h3>Danger Zone</h3>
                <p>
                     Doing this will permanatly all your data including( your cart, your listing, ect).
                </p>
                <a href="delete_account.php" class="btn-danger">
                    Delete My Account
                </a>
            </div>
        </div>

        <div class="card">
            <h2>My activity</h2>
            <div class="stats">
                <div class="stat">
                    <span class="stat-label">Items posted</span>
                    <span class="stat-value"><?= $total_listings ?></span>
                </div>
                <div class="stat">
                    <span class="stat-label">Purchases</span>
                    <span class="stat-value"><?= $total_purchases ?></span>
                </div>
            </div>

            <div class="links-list">
                <a href="my_listings.php">&raquo; View items I put up for sale</a>
                <a href="purchases.php">&raquo; View what I purchased</a>
                <a href="cart.php">&raquo; View my cart</a>
            </div>
        </div>
    </section>
</main>

<script>
  function toggleNav() {
    var x = document.getElementById("myTopnav");
    if (x.className === "topnav") x.className += " responsive";
    else x.className = "topnav";
  }

  const avatarBox = document.getElementById("avatarBox");
  const photoInput = document.getElementById("profile_photo");

  avatarBox.addEventListener("click", () => photoInput.click());

  photoInput.addEventListener("change", (e) => {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(ev){
      avatarBox.innerHTML = `
        <img id="avatarImg" src="${ev.target.result}"
             style="width:100%;height:100%;object-fit:cover;">
        <div class="edit-badge"><i class="fa-solid fa-camera"></i></div>
      `;
    };
    reader.readAsDataURL(file);
  });
</script>

</body>
</html>
