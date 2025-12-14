<?php
// post.php – create a new item for sale using your existing UI

// Show errors while developing (you can remove later)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// central config: starts session + DB
require_once __DIR__ . '/../admin/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id     = (int)$_SESSION['user_id'];
$success_msg = '';
$error_msg   = '';

// HANDLE FORM SUBMIT
// HANDLE FORM SUBMIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = trim($_POST['price'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $condition   = trim($_POST['condition'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $stock       = 1;

    if ($title === '' || $price === '' || $category === '' || $condition === '' || $description === '') {
        $error_msg = "Please fill in all required fields.";
    } elseif (!is_numeric($price) || (float)$price <= 0) {
        $error_msg = "Price must be a positive number.";
    } else {

        /* ===========================
           1) MULTI IMAGE UPLOAD
           =========================== */
        $upload_dir_fs  = __DIR__ . '/../assets/img/';   // filesystem path
        $upload_dir_url = '/Ecommerce/assets/img/';      // URL path

        if (!is_dir($upload_dir_fs)) {
            mkdir($upload_dir_fs, 0777, true);
        }

        $uploaded_urls = []; // store all uploaded image URLs here

        if (!empty($_FILES['images']['name'][0])) {
            $total_files = count($_FILES['images']['name']);
            $max_files   = 5;

            for ($i = 0; $i < $total_files && $i < $max_files; $i++) {
                if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                    continue; // skip failed file
                }

                $original_name = basename($_FILES['images']['name'][$i]);
                $ext           = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

                // basic extension guard
                $allowed = ['jpg','jpeg','png','gif','webp'];
                if (!in_array($ext, $allowed)) {
                    continue;
                }

                $safe_name     = preg_replace(
                    '/[^a-zA-Z0-9_\-]/',
                    '_',
                    pathinfo($original_name, PATHINFO_FILENAME)
                );

                $new_filename  = $safe_name . '_' . time() . '_' . $i . '.' . $ext;

                $target_fs  = $upload_dir_fs . $new_filename;
                $target_url = $upload_dir_url . $new_filename;

                if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $target_fs)) {
                    $uploaded_urls[] = $target_url;
                }
            }
        }

        // first image is cover for items table
        $cover_image_url = $uploaded_urls[0] ?? null;


        /* ===========================
           2) INSERT INTO items
           =========================== */
        $sql = "
            INSERT INTO items (
                seller_id,
                title,
                description,
                price,
                stock,
                image,
                status,
                category_label,
                item_condition,
                location
            )
            VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $error_msg = "Database error: " . $conn->error;
        } else {
            $price_float = (float)$price;
            $stmt->bind_param(
                "issdissss",
                $user_id,
                $title,
                $description,
                $price_float,
                $stock,
                $cover_image_url,
                $category,
                $condition,
                $location
            );

            if ($stmt->execute()) {

                // ✅ newly created item id
                $new_item_id = $stmt->insert_id;
                $stmt->close();


                /* ===========================
                   3) INSERT EXTRA IMAGES
                      into item_images
                   =========================== */
                if (!empty($uploaded_urls)) {
                    // start from 0 or 1?
                    // if cover already saved in items.image,
                    // we insert ALL images into item_images OR only 2nd+ images.
                    // Here: only 2nd+ images (index 1 onward).
                    $img_stmt = $conn->prepare("
                        INSERT INTO item_images (item_id, image_path)
                        VALUES (?, ?)
                    ");

                    if ($img_stmt) {
                        for ($j = 1; $j < count($uploaded_urls); $j++) {
                            $img_url = $uploaded_urls[$j];
                            $img_stmt->bind_param("is", $new_item_id, $img_url);
                            $img_stmt->execute(); // ignore minor fails
                        }
                        $img_stmt->close();
                    }
                }

                // ✅ SUCCESS redirect
                header("Location: home.php");
                exit;

            } else {
                $error_msg = "Could not save item. Please try again.";
                $stmt->close();
            }
        }
    }
}


// fallback defaults so PHP vars exist for echoing
if (!isset($title))       $title = '';
if (!isset($description)) $description = '';
if (!isset($price))       $price = '';
if (!isset($category))    $category = '';
if (!isset($condition))   $condition = '';
if (!isset($location))    $location = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Post a Product</title>
  <link rel="stylesheet" href="/Ecommerce/User/css/post.css" />
</head>
<body class="page">
  <main class="container">
    <!-- messages from PHP -->
    <?php if (!empty($success_msg)): ?>
      <div class="alert success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
      <div class="alert error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <form
      id="postForm"
      class="card"
      action="post.php"
      method="post"
      enctype="multipart/form-data"
      novalidate
    >
      <header class="card-header">
        <h1 class="title">Post a Product</h1>
        <p class="subtitle">Fill in the details below to list your item for sale.</p>
      </header>

      <!-- Basic Details -->
      <section class="grid two">
        <div class="field">
          <label for="title">Title <span class="req">*</span></label>
          <input
            id="title"
            name="title"
            type="text"
            required
            minlength="3"
            maxlength="120"
            placeholder="e.g., Vintage Leather Jacket"
            value="<?= htmlspecialchars($title) ?>"
          />
          <small class="hint" id="titleCount"><?= strlen($title) ?>/120</small>
        </div>

        <!-- Photos -->
        <section class="field">
          <label for="images">Photos (up to 5)</label>
          <input
            id="images"
            name="images[]"
            type="file"
            accept="image/*"
            multiple
          />
          <small class="hint">First image becomes the cover.</small>
          <div id="preview" class="preview"></div>
        </section>

        <div class="field">
          <label for="category">Category <span class="req">*</span></label>
          <select id="category" name="category" required>
            <option value="" disabled <?= $category === '' ? 'selected' : '' ?>>Choose a category</option>
            <?php
            $cats = [
                "Clothes",
                "Shoes",
                "Accessories",
                "Electronics",
                "Books",
                "Home & Kitchen",
                "Toys",
                "Furniture",
                "Other"
            ];
            foreach ($cats as $c):
            ?>
              <option value="<?= htmlspecialchars($c) ?>" <?= ($category === $c) ? 'selected' : '' ?>>
                <?= htmlspecialchars($c) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="price">Price (MMK) <span class="req">*</span></label>
          <input
            id="price"
            name="price"
            type="number"
            min="100"
            step="100"
            placeholder="000.0"
            required
            value="<?= htmlspecialchars($price) ?>"
          />
        </div>

        <div class="field">
          <label for="condition">Condition <span class="req">*</span></label>
          <select id="condition" name="condition" required>
            <option value="" disabled <?= $condition === '' ? 'selected' : '' ?>>Choose one</option>
            <?php
            $conds = ["New", "Like New", "Good", "Fair", "Use"];
            foreach ($conds as $cond):
            ?>
              <option value="<?= htmlspecialchars($cond) ?>" <?= ($condition === $cond) ? 'selected' : '' ?>>
                <?= htmlspecialchars($cond) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="location">Location</label>
          <input
            id="location"
            name="location"
            type="text"
            placeholder="City, Country"
            maxlength="80"
            value="<?= htmlspecialchars($location) ?>"
          />
        </div>
      </section>

      <!-- Description -->
      <section class="field">
        <label for="description">Description <span class="req">*</span></label>
        <textarea
          id="description"
          name="description"
          rows="6"
          required
          maxlength="10000"
          placeholder="Add key features, defects, size, model, etc."
        ><?= htmlspecialchars($description) ?></textarea>
        <small class="hint" id="descCount"><?= strlen($description) ?>/10000</small>
      </section>

      <!-- Actions -->
      <footer class="actions">
        <button type="submit" class="btn primary" id="submitBtn">Post</button>
        <a href="home.php" class="btn ghost">Cancel</a>
        <div id="errorBox" class="error hidden"></div>
      </footer>
    </form>
  </main>

  <script>
    // Counters
    const titleInput = document.getElementById('title');
    const titleCount = document.getElementById('titleCount');
    titleInput.addEventListener('input', () => {
      titleCount.textContent = titleInput.value.length + '/120';
    });

    const desc = document.getElementById('description');
    const descCount = document.getElementById('descCount');
    desc.addEventListener('input', () => {
      descCount.textContent = desc.value.length + '/10000';
    });

    // Image preview (max 5)
    const input = document.getElementById('images');
    const preview = document.getElementById('preview');
    if (input) {
      input.addEventListener('change', () => {
        preview.innerHTML = '';
        const files = Array.from(input.files).slice(0, 5);
        files.forEach(file => {
          const url = URL.createObjectURL(file);
          const fig = document.createElement('figure');
          fig.className = 'thumb';
          fig.innerHTML = '<img src="' + url + '" alt="preview"><figcaption>' + file.name + '</figcaption>';
          preview.appendChild(fig);
        });
      });
    }

    // Front-end guard
    const form = document.getElementById('postForm');
    const errorBox = document.getElementById('errorBox');
    const submitBtn = document.getElementById('submitBtn');
    form.addEventListener('submit', (e) => {
      errorBox.classList.add('hidden');
      errorBox.textContent = '';

      if (!form.reportValidity()) {
        e.preventDefault();
        return;
      }

      // Prevent multiple clicks
      submitBtn.disabled = true;
      submitBtn.textContent = 'Publishing...';
    });
  </script>
</body>
</html>
