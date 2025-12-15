<?php
// email_helper.php – sends voucher emails using PHPMailer (buyer + seller)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';


/**
 * Internal helper: build a configured PHPMailer instance
 */
function makeMailer(): PHPMailer
{
  $mail = new PHPMailer(true);

  $mail->SMTPDebug = 0;

  $mail->isSMTP();
  $mail->Host       = 'smtp.gmail.com';
  $mail->SMTPAuth   = true;
  $mail->Username   = 'yudigar2005@gmail.com';   // your Gmail
  $mail->Password   = 'zjbhxqsvecuxvhha';        // app password (16 chars, no spaces)
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;  // SSL
  $mail->Port       = 465;
  $mail->CharSet    = 'UTF-8';

  // From must match the Gmail account
  $mail->setFrom('yudigar2005@gmail.com', 'Second Hand Products Market');

  return $mail;
}


/**
 * Send voucher email to buyer
 */
function sendVoucherEmail(
  string $toEmail,
  string $toName,
  string $voucherCode,
  array  $items,
  float  $totalAmount
): bool {
  try {
    $mail = makeMailer();

    $mail->addAddress($toEmail, $toName);
    $mail->isHTML(true);
    $mail->Subject = 'Your Order Voucher - Second Hand Products Market';

    // Build HTML body
    $html  = "<h2>Thank you for your purchase, " . htmlspecialchars($toName) . "!</h2>";

    if (!empty($voucherCode)) {
      $html .= "<p><strong>Voucher #:</strong> " . htmlspecialchars($voucherCode) . "</p>";
    }

    $html .= "<table border='1' cellspacing='0' cellpadding='6' style='border-collapse:collapse; width:100%;'>
                    <thead>
                      <tr>
                        <th>Item</th>
                        <th>Price (MMK)</th>
                        <th>Qty</th>
                        <th>Total (MMK)</th>
                      </tr>
                    </thead>
                    <tbody>";

    foreach ($items as $it) {
      $html .= "<tr>
                        <td>" . htmlspecialchars($it['title']) . "</td>
                        <td>" . number_format($it['price'], 0) . "</td>
                        <td>" . (int)$it['quantity'] . "</td>
                        <td>" . number_format($it['line_total'], 0) . "</td>
                      </tr>";
    }

    $html .= "  </tbody>
                    <tfoot>
                      <tr>
                        <th colspan='3' style='text-align:right;'>Grand Total</th>
                        <th>" . number_format($totalAmount, 0) . " MMK</th>
                      </tr>
                    </tfoot>
                  </table>";

    $html .= "<p>You can also print this voucher directly from the website.</p>";

    $mail->Body    = $html;
    $mail->AltBody = "Thank you for your purchase.\nVoucher: {$voucherCode}\nTotal: " .
      number_format($totalAmount, 0) . " MMK";

    $mail->send();
    return true;
  } catch (Exception $e) {
    // For debugging you can uncomment:
    // echo '<pre style="color:red;">Mailer Error: ' . htmlspecialchars($e->getMessage()) . '</pre>';
    return false;
  }
}


/**
 * Send seller email after buyer places order
 * (Seller receives ONLY their sold items + buyer shipping info)
 */
function sendSellerSaleEmail(
  string $sellerEmail,
  string $sellerName,
  string $buyerName,
  string $buyerPhone,
  string $buyerAddress,
  string $voucherCode,
  array  $items,
  float  $sellerTotal
): bool {
  try {
    $mail = makeMailer();

    $mail->addAddress($sellerEmail, $sellerName);
    $mail->isHTML(true);
    $mail->Subject = "You made a sale! Voucher {$voucherCode}";

    $html  = "<h2>Hello " . htmlspecialchars($sellerName) . " 👋</h2>";
    $html .= "<p>You have a new order on <strong>Second Hand Products Market</strong>.</p>";

    $html .= "<p><strong>Voucher #:</strong> " . htmlspecialchars($voucherCode) . "</p>";

    $html .= "<h3>Buyer Shipping Info</h3>";
    $html .= "<p><strong>Name:</strong> " . htmlspecialchars($buyerName) . "</p>";
    $html .= "<p><strong>Phone:</strong> " . htmlspecialchars($buyerPhone) . "</p>";
    $html .= "<p><strong>Address:</strong><br>" . nl2br(htmlspecialchars($buyerAddress)) . "</p>";

    $html .= "<h3>Your Sold Items</h3>";
    $html .= "<table border='1' cellspacing='0' cellpadding='6' style='border-collapse:collapse; width:100%;'>
                    <thead>
                      <tr>
                        <th>Item</th>
                        <th>Price (MMK)</th>
                        <th>Qty</th>
                        <th>Total (MMK)</th>
                      </tr>
                    </thead>
                    <tbody>";

    foreach ($items as $it) {
      $html .= "<tr>
                        <td>" . htmlspecialchars($it['title']) . "</td>
                        <td>" . number_format($it['price'], 0) . "</td>
                        <td>" . (int)$it['quantity'] . "</td>
                        <td>" . number_format($it['line_total'], 0) . "</td>
                      </tr>";
    }

    $html .= "  </tbody>
                    <tfoot>
                      <tr>
                        <th colspan='3' style='text-align:right;'>Your Total</th>
                        <th>" . number_format($sellerTotal, 0) . " MMK</th>
                      </tr>
                    </tfoot>
                  </table>";

    $html .= "<p>Please prepare the items for delivery/pickup.</p>";

    $mail->Body    = $html;
    $mail->AltBody = "New sale!\nVoucher: {$voucherCode}\nBuyer: {$buyerName}\nPhone: {$buyerPhone}\nTotal: " .
      number_format($sellerTotal, 0) . " MMK";

    $mail->send();
    return true;
  } catch (Exception $e) {
    return false;
  }
}
