<?php

$host     = 'localhost';
$username = 'root';
$password = '';
$dbname   = 'ecommerce';
$port     = 3308;


$conn = new mysqli($host, $username, $password, '', $port);
if ($conn->connect_errno) {
    die("Fail to connect MySQL: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');


function run_or_die(mysqli $conn, string $sql, string $label = ''): bool
{
    if ($conn->query($sql) === false) {
        echo "\n\n SQL Error while {$label}:\n{$sql}\nMySQL says: {$conn->error}\n";
        return false;
    }
    return true;
}

function create_database(mysqli $conn, string $dbname): bool
{
    $sqls = [
        "CREATE DATABASE IF NOT EXISTS `{$dbname}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci",
        "ALTER DATABASE `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
    ];
    foreach ($sqls as $sql) {
        if (!run_or_die($conn, $sql, "creating database {$dbname}")) return false;
    }
    return true;
}
if (!create_database($conn, $dbname)) {
    die("Could not create/select database.");
}


if (!$conn->select_db($dbname)) {
    die(" Could not select DB `{$dbname}`: " . $conn->error);
}
$conn->query("SET sql_mode=''");
$conn->set_charset('utf8mb4');


function ensure_schema(mysqli $conn): bool
{
    // USERS (compatible with your login.php + profile.php)
    $sql = "CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50) NOT NULL,
    email           VARCHAR(150) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('buyer','seller','both','admin') NOT NULL DEFAULT 'buyer',

    profile_image   VARCHAR(255) DEFAULT NULL,
    phone           VARCHAR(50) DEFAULT NULL,
    address         TEXT DEFAULT NULL,

    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_users_username (username),
    UNIQUE KEY uk_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";


    // CATEGORIES (optional, for grouping items)
    $sql = "CREATE TABLE IF NOT EXISTS categories (
        id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        UNIQUE KEY uk_categories_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    if (!run_or_die($conn, $sql, "ensuring table categories")) return false;

    // ITEMS (things users put up for sale)
    $sql = "CREATE TABLE IF NOT EXISTS items (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        seller_id       INT UNSIGNED NOT NULL,
        category_id     INT UNSIGNED DEFAULT NULL,
        title           VARCHAR(255) NOT NULL,
        description     TEXT,
        price           DECIMAL(10,2) NOT NULL,
        stock           INT NOT NULL DEFAULT 1,
        image           VARCHAR(255) DEFAULT NULL,
        -- new columns used by your PHP code:
        category_label  VARCHAR(80) DEFAULT NULL,
        item_condition  VARCHAR(30) DEFAULT NULL,
        location        VARCHAR(120) DEFAULT NULL,

        status      ENUM('active','inactive','sold') NOT NULL DEFAULT 'active',
        created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

        CONSTRAINT fk_items_seller
            FOREIGN KEY (seller_id) REFERENCES users(id)
            ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_items_category
            FOREIGN KEY (category_id) REFERENCES categories(id)
            ON DELETE SET NULL ON UPDATE CASCADE,

        KEY ix_items_seller    (seller_id),
        KEY ix_items_category  (category_id),
        KEY ix_items_status    (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    if (!run_or_die($conn, $sql, "ensuring table items")) return false;
    // CART ITEMS (user's shopping cart)
    $sql = "CREATE TABLE IF NOT EXISTS cart_items (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id    INT UNSIGNED NOT NULL,
        item_id    INT UNSIGNED NOT NULL,
        quantity   INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

        CONSTRAINT fk_cart_user
            FOREIGN KEY (user_id) REFERENCES users(id)
            ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_cart_item
            FOREIGN KEY (item_id) REFERENCES items(id)
            ON DELETE CASCADE ON UPDATE CASCADE,

        KEY ix_cart_user (user_id),
        KEY ix_cart_item (item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    if (!run_or_die($conn, $sql, "ensuring table cart_items")) return false;

    // ORDERS (each checkout)
    $sql = "CREATE TABLE IF NOT EXISTS orders (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        buyer_id        INT UNSIGNED NOT NULL,
        subtotal_amount DECIMAL(10,2) NOT NULL,
        discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        total_amount    DECIMAL(10,2) NOT NULL,
        voucher_code    VARCHAR(50) DEFAULT NULL,
        status          ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

        CONSTRAINT fk_orders_buyer
            FOREIGN KEY (buyer_id) REFERENCES users(id)
            ON DELETE CASCADE ON UPDATE CASCADE,

        KEY ix_orders_buyer (buyer_id),
        KEY ix_orders_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    if (!run_or_die($conn, $sql, "ensuring table orders")) return false;

    // ORDER ITEMS (items inside each order)
    $sql = "CREATE TABLE IF NOT EXISTS order_items (
        id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id INT UNSIGNED NOT NULL,
        item_id  INT UNSIGNED NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        price    DECIMAL(10,2) NOT NULL,

        CONSTRAINT fk_orderitems_order
            FOREIGN KEY (order_id) REFERENCES orders(id)
            ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_orderitems_item
            FOREIGN KEY (item_id) REFERENCES items(id)
            ON DELETE CASCADE ON UPDATE CASCADE,

        KEY ix_oi_order (order_id),
        KEY ix_oi_item  (item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    if (!run_or_die($conn, $sql, "ensuring table order_items")) return false;

    // VOUCHERS (coupon codes)
    $sql = "CREATE TABLE IF NOT EXISTS vouchers (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code            VARCHAR(50) NOT NULL UNIQUE,
        discount_type   ENUM('percent','fixed') NOT NULL,
        discount_value  DECIMAL(10,2) NOT NULL,
        min_order_amount DECIMAL(10,2) DEFAULT NULL,
        max_uses        INT DEFAULT NULL,
        used_count      INT NOT NULL DEFAULT 0,
        valid_from      DATETIME DEFAULT NULL,
        valid_to        DATETIME DEFAULT NULL,
        is_active       TINYINT(1) NOT NULL DEFAULT 1,
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    if (!run_or_die($conn, $sql, "ensuring table vouchers")) return false;

    // VOUCHER REDEMPTIONS (who used what voucher)
    $sql = "CREATE TABLE IF NOT EXISTS voucher_redemptions (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        voucher_id INT UNSIGNED NOT NULL,
        order_id   INT UNSIGNED NOT NULL,
        user_id    INT UNSIGNED NOT NULL,
        used_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

        CONSTRAINT fk_vr_voucher
            FOREIGN KEY (voucher_id) REFERENCES vouchers(id)
            ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_vr_order
            FOREIGN KEY (order_id) REFERENCES orders(id)
            ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_vr_user
            FOREIGN KEY (user_id) REFERENCES users(id)
            ON DELETE CASCADE ON UPDATE CASCADE,

        KEY ix_vr_user (user_id),
        KEY ix_vr_voucher (voucher_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    if (!run_or_die($conn, $sql, "ensuring table voucher_redemptions")) return false;

    return true;
}

if (!ensure_schema($conn)) {
    die("❌ Failed to ensure DB schema.");
}
