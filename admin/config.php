<?php
// Central include for DB + helpers

// Start session once here so any page that includes this
// will have session ready.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DB bootstrap (must define $conn as a mysqli connection)
require_once __DIR__ . '/../Database/db_connect.php';


// ---------------- Admin Gate Strategy (UPDATED) ----------------
// ✅ Now we support a real admin role stored in users.role
// (your DB screenshot shows role='admin' already)

// (Optional) extra whitelist for backup admins by email.
// You can keep this or remove it if you want admin-by-role only.
const ADMIN_EMAILS = [
    'admin@gmail.com',     // your main admin
    // 'you@yourdomain.com',
];

// Helper: is the current user considered an admin?
function is_admin(): bool {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        return false;
    }

    // ✅ 1) Primary rule: role === 'admin'
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return true;
    }

    // ✅ 2) Backup rule: whitelist email (optional)
    $email = $_SESSION['email'] ?? '';
    if ($email && in_array(strtolower($email), array_map('strtolower', ADMIN_EMAILS), true)) {
        return true;
    }

    return false;
}
