<?php
namespace DiveConnect;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class Mailer
{
    private $mail;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);

        // SMTP CONFIG
        $this->mail->isSMTP();
        $this->mail->Host       = 'smtp.gmail.com';
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = 'diveconnect25@gmail.com';
        $this->mail->Password   = 'czui umnx uosp xjec';
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = 587;

        // CHARACTER ENCODING FIX
        $this->mail->CharSet = 'UTF-8';
        $this->mail->Encoding = 'base64';
        
        $this->mail->setFrom('diveconnect25@gmail.com', 'DiveConnect Team');
    }

    /**
     * Send Email Verification Link
     */
    public function sendVerification($to, $name, $token)
    {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($to, $name);
            $this->mail->isHTML(true);
            $this->mail->Subject = "Verify Your Email - DiveConnect";

            $verifyLink = "https://diveconnect.site/user/verify_email.php?token=" . urlencode($token);

            $this->mail->Body = "
            <html>
            <head>
            <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
            <style>
                body {
                    font-family: 'Segoe UI', Arial, sans-serif;
                    background-color: #f5f9ff;
                    margin: 0;
                    padding: 30px;
                }
                .container {
                    max-width: 620px;
                    margin: auto;
                    background: #ffffff;
                    border: 1px solid #dbe7f4;
                    border-radius: 10px;
                    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
                    overflow: hidden;
                }
                .header {
                    text-align: center;
                    padding: 25px 20px;
                    background: #0077b6;
                    color: #fff;
                }
                .content {
                    padding: 30px;
                    color: #333;
                    line-height: 1.6;
                }
                .verify-btn {
                    display: inline-block;
                    background: linear-gradient(135deg, #0077b6, #005f8c);
                    color: white;
                    font-size: 18px;
                    font-weight: bold;
                    text-align: center;
                    padding: 16px 32px;
                    margin: 25px 0;
                    border-radius: 8px;
                    text-decoration: none;
                    font-family: 'Segoe UI', sans-serif;
                }
                .note {
                    background-color: #eef8ff;
                    border: 1px solid #b9e0ff;
                    padding: 15px;
                    margin: 20px 0;
                    border-radius: 6px;
                    text-align: center;
                }
                .footer {
                    background: #f1f5fb;
                    text-align: center;
                    padding: 15px;
                    font-size: 12px;
                    color: #888;
                }
                .warning {
                    color: #d62828;
                    font-weight: bold;
                }
            </style>
            </head>
            <body>
            <div class='container'>
                <div class='header'>
                    <h2>🔐 Verify Your Email Address</h2>
                </div>
                <div class='content'>
                    <p>Hi <strong>$name</strong>,</p>
                    <p>Thank you for registering with DiveConnect! Click the button below to verify your email address:</p>

                    <div style='text-align: center;'>
                        <a href='$verifyLink' class='verify-btn'>
                            Verify Email Address
                        </a>
                    </div>

                    <div class='note'>
                        <p class='warning'>⚠️ This verification link will expire in 24 hours</p>
                        <p>After verification, your account will be pending admin approval. You will be notified via email once approved.</p>
                    </div>

                    <p>If you didn't request this verification, please ignore this email.</p>

                    <p style='margin-top:20px; color:#666;'>This is an automated email. Please do not reply.</p>
                </div>
                <div class='footer'>
                    &copy; " . date('Y') . " DiveConnect. All rights reserved.
                </div>
            </div>
            </body>
            </html>";

            $this->mail->AltBody = "Hi $name,\n\nThank you for registering with DiveConnect! Please verify your email by clicking this link: $verifyLink\n\nThis verification link will expire in 24 hours.\n\nIf you didn't request this verification, please ignore this email.";

            return $this->mail->send();
        } catch (Exception $e) {
            error_log('Verification Mailer Error: ' . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Send notification to admin about new user registration
     */
    public function sendAdminNewUserNotification($adminEmail, $userName, $userEmail)
    {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($adminEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = "New User Registration - Needs Approval";

            $this->mail->Body = "
            <html>
            <head>
            <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
            <style>
                body {
                    font-family: 'Segoe UI', Arial, sans-serif;
                    background-color: #f5f9ff;
                    margin: 0;
                    padding: 30px;
                }
                .container {
                    max-width: 620px;
                    margin: auto;
                    background: #ffffff;
                    border: 1px solid #dbe7f4;
                    border-radius: 10px;
                    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
                    overflow: hidden;
                }
                .header {
                    text-align: center;
                    padding: 25px 20px;
                    background: #0077b6;
                    color: #fff;
                }
                .content {
                    padding: 30px;
                    color: #333;
                    line-height: 1.6;
                }
                .user-info {
                    background-color: #eef8ff;
                    border: 1px solid #b9e0ff;
                    padding: 15px;
                    margin: 20px 0;
                    border-radius: 6px;
                }
                .btn {
                    display: inline-block;
                    background: #0077b6;
                    color: #fff;
                    padding: 12px 24px;
                    border-radius: 6px;
                    text-decoration: none;
                    font-weight: 500;
                    transition: 0.3s;
                }
                .btn:hover {
                    background: #005f8c;
                }
                .footer {
                    background: #f1f5fb;
                    text-align: center;
                    padding: 15px;
                    font-size: 12px;
                    color: #888;
                }
            </style>
            </head>
            <body>
            <div class='container'>
                <div class='header'>
                    <h2>📋 New User Registration</h2>
                </div>
                <div class='content'>
                    <p>Hello Admin,</p>
                    <p>A new user has registered and verified their email address. The account is now pending your approval.</p>

                    <div class='user-info'>
                        <p><strong>User Name:</strong> $userName</p>
                        <p><strong>Email:</strong> $userEmail</p>
                        <p><strong>Registration Date:</strong> " . date('Y-m-d H:i:s') . "</p>
                        <p><strong>Status:</strong> 📝 Pending Approval</p>
                    </div>

                    <p>Please review the user's information and documents in the admin panel to approve or reject their account.</p>

                    <div style='text-align: center; margin: 25px 0;'>
                        <a href='https://diveconnect.site/admin/login_admin.php' class='btn'>
                            🔍 Review in Admin Panel
                        </a>
                    </div>

                    <p style='margin-top:20px; color:#666;'>This is an automated notification from DiveConnect.</p>
                </div>
                <div class='footer'>
                    &copy; " . date('Y') . " DiveConnect. All rights reserved.
                </div>
            </div>
            </body>
            </html>";

            $this->mail->AltBody = "New User Registration\n\nHello Admin,\n\nA new user has registered and verified their email address.\n\nUser Name: $userName\nEmail: $userEmail\nRegistration Date: " . date('Y-m-d H:i:s') . "\nStatus: Pending Approval\n\nPlease review in admin panel: https://diveconnect.site/admin/login_admin.php";

            return $this->mail->send();
        } catch (Exception $e) {
            error_log('Admin Notification Mailer Error: ' . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Send User Account Approval Status
     */
    public function sendUserApprovalStatus($to, $name, $status, $reason = '')
    {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($to, $name);
            $this->mail->isHTML(true);

            if ($status === 'approved') {
                $this->mail->Subject = "🎉 Your DiveConnect Account Has Been Approved!";
                $this->mail->Body = "
                <html>
                <head>
                <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
                <style>
                    body {
                        font-family: 'Segoe UI', Arial, sans-serif;
                        background-color: #f0f9f0;
                        margin: 0;
                        padding: 30px;
                    }
                    .container {
                        max-width: 620px;
                        margin: auto;
                        background: #ffffff;
                        border: 1px solid #c3e6cb;
                        border-radius: 10px;
                        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
                        overflow: hidden;
                    }
                    .header {
                        text-align: center;
                        padding: 25px 20px;
                        background: linear-gradient(135deg, #10b981, #059669);
                        color: #fff;
                    }
                    .content {
                        padding: 30px;
                        color: #333;
                        line-height: 1.6;
                    }
                    .highlight {
                        background-color: #d1fae5;
                        border: 1px solid #a7f3d0;
                        padding: 15px;
                        margin: 20px 0;
                        border-radius: 6px;
                    }
                    .btn {
                        display: inline-block;
                        background: #10b981;
                        color: #fff;
                        padding: 12px 24px;
                        border-radius: 6px;
                        text-decoration: none;
                        font-weight: 500;
                        transition: 0.3s;
                    }
                    .btn:hover {
                        background: #059669;
                    }
                    .footer {
                        background: #ecfdf5;
                        text-align: center;
                        padding: 15px;
                        font-size: 12px;
                        color: #888;
                    }
                </style>
                </head>
                <body>
                <div class='container'>
                    <div class='header'>
                        <h2>✅ Account Approved!</h2>
                    </div>
                    <div class='content'>
                        <p>Hi <strong>$name</strong>,</p>
                        <p>Great news! Your DiveConnect account has been <strong>approved</strong> by our admin team.</p>

                        <div class='highlight'>
                            <p><strong>🎯 What's Next?</strong></p>
                            <p>• You can now login to your account</p>
                            <p>• Explore diving destinations</p>
                            <p>• Book with professional dive masters</p>
                            <p>• Join our diving community</p>
                        </div>

                        <div style='text-align: center; margin: 25px 0;'>
                            <a href='https://diveconnect.site/user/user_login.php' class='btn'>
                                🚀 Login to Your Account
                            </a>
                        </div>

                        <p>We're excited to have you onboard and can't wait to help you explore amazing underwater adventures!</p>

                        <p style='margin-top:20px; color:#666;'>This is an automated email. Please do not reply.</p>
                    </div>
                    <div class='footer'>
                        &copy; " . date('Y') . " DiveConnect. All rights reserved.
                    </div>
                </div>
                </body>
                </html>";

                $this->mail->AltBody = "Hi $name,\n\nGreat news! Your DiveConnect account has been APPROVED by our admin team.\n\nWhat's Next?\n• You can now login to your account\n• Explore diving destinations\n• Book with professional dive masters\n• Join our diving community\n\nLogin here: https://diveconnect.site/user/user_login.php\n\nWe're excited to have you onboard!";
            } else {
                $this->mail->Subject = "❌ Your DiveConnect Account Application Status";
                $this->mail->Body = "
                <html>
                <head>
                <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
                <style>
                    body {
                        font-family: 'Segoe UI', Arial, sans-serif;
                        background-color: #fef2f2;
                        margin: 0;
                        padding: 30px;
                    }
                    .container {
                        max-width: 620px;
                        margin: auto;
                        background: #ffffff;
                        border: 1px solid #fecaca;
                        border-radius: 10px;
                        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
                        overflow: hidden;
                    }
                    .header {
                        text-align: center;
                        padding: 25px 20px;
                        background: linear-gradient(135deg, #ef4444, #dc2626);
                        color: #fff;
                    }
                    .content {
                        padding: 30px;
                        color: #333;
                        line-height: 1.6;
                    }
                    .highlight {
                        background-color: #fee2e2;
                        border: 1px solid #fecaca;
                        padding: 15px;
                        margin: 20px 0;
                        border-radius: 6px;
                    }
                    .btn {
                        display: inline-block;
                        background: #ef4444;
                        color: #fff;
                        padding: 12px 24px;
                        border-radius: 6px;
                        text-decoration: none;
                        font-weight: 500;
                        transition: 0.3s;
                    }
                    .btn:hover {
                        background: #dc2626;
                    }
                    .footer {
                        background: #fef2f2;
                        text-align: center;
                        padding: 15px;
                        font-size: 12px;
                        color: #888;
                    }
                </style>
                </head>
                <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Account Application Update</h2>
                    </div>
                    <div class='content'>
                        <p>Hi <strong>$name</strong>,</p>
                        <p>We regret to inform you that your DiveConnect account application has been <strong>rejected</strong>.</p>

                        <div class='highlight'>
                            <p><strong>📋 Reason:</strong></p>
                            <p>" . nl2br(htmlspecialchars($reason)) . "</p>
                        </div>

                        <p>If you believe this is an error or would like to appeal this decision, please contact our support team for assistance.</p>

                        <div style='text-align: center; margin: 25px 0;'>
                            <a href='mailto:diveconnect25@gmail.com' class='btn'>
                                📞 Contact Support
                            </a>
                        </div>

                        <p style='margin-top:20px; color:#666;'>This is an automated email. Please do not reply.</p>
                    </div>
                    <div class='footer'>
                        &copy; " . date('Y') . " DiveConnect. All rights reserved.
                    </div>
                </div>
                </body>
                </html>";

                $this->mail->AltBody = "Hi $name,\n\nWe regret to inform you that your DiveConnect account application has been REJECTED.\n\nReason: $reason\n\nIf you believe this is an error, please contact our support team: diveconnect25@gmail.com";
            }

            return $this->mail->send();
        } catch (Exception $e) {
            error_log('User Approval Mailer Error: ' . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Send New Booking Notification to Diver
     */
    public function sendNewBookingNotification($diverEmail, $diverName, $userName, $bookingDate, $paxCount, $totalAmount)
    {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($diverEmail, $diverName);
            $this->mail->isHTML(true);
            $this->mail->Subject = "📅 New Booking Request - DiveConnect";

            $this->mail->Body = "
            <html>
            <head>
            <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
            <style>
                body {
                    font-family: 'Segoe UI', Arial, sans-serif;
                    background-color: #f5f9ff;
                    margin: 0;
                    padding: 30px;
                }
                .container {
                    max-width: 620px;
                    margin: auto;
                    background: #ffffff;
                    border: 1px solid #dbe7f4;
                    border-radius: 10px;
                    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
                    overflow: hidden;
                }
                .header {
                    text-align: center;
                    padding: 25px 20px;
                    background: linear-gradient(135deg, #0077b6, #005f8c);
                    color: #fff;
                }
                .content {
                    padding: 30px;
                    color: #333;
                    line-height: 1.6;
                }
                .booking-details {
                    background-color: #eef8ff;
                    border: 1px solid #b9e0ff;
                    padding: 20px;
                    margin: 20px 0;
                    border-radius: 8px;
                }
                .detail-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                    padding: 5px 0;
                }
                .detail-label {
                    font-weight: 600;
                    color: #005f8c;
                }
                .btn {
                    display: inline-block;
                    background: linear-gradient(135deg, #0077b6, #005f8c);
                    color: white;
                    padding: 14px 28px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-weight: 600;
                    font-size: 16px;
                    transition: 0.3s;
                }
                .btn:hover {
                    background: linear-gradient(135deg, #005f8c, #004a6d);
                }
                .footer {
                    background: #f1f5fb;
                    text-align: center;
                    padding: 15px;
                    font-size: 12px;
                    color: #888;
                }
                .urgent {
                    color: #d62828;
                    font-weight: 600;
                }
            </style>
            </head>
            <body>
            <div class='container'>
                <div class='header'>
                    <h2>🎯 New Booking Request!</h2>
                </div>
                <div class='content'>
                    <p>Hi <strong>$diverName</strong>,</p>
                    <p>You have received a new booking request! Here are the details:</p>

                    <div class='booking-details'>
                        <div class='detail-row'>
                            <span class='detail-label'>👤 Client Name:</span>
                            <span>$userName</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>📅 Booking Date:</span>
                            <span>$bookingDate</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>👥 Number of Divers:</span>
                            <span>$paxCount divers</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>💰 Total Amount:</span>
                            <span>₱" . number_format($totalAmount, 2) . "</span>
                        </div>
                    </div>

                    <p class='urgent'>⏰ Please respond to this booking request within 24 hours.</p>

                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='https://diveconnect.site/diver/login_diver.php' class='btn'>
                            📋 Review Booking in Dashboard
                        </a>
                    </div>

                    <p>You can approve or decline this booking request from your diver dashboard.</p>

                    <p style='margin-top:20px; color:#666;'>This is an automated notification from DiveConnect.</p>
                </div>
                <div class='footer'>
                    &copy; " . date('Y') . " DiveConnect. All rights reserved.
                </div>
            </div>
            </body>
            </html>";

            $this->mail->AltBody = "New Booking Request\n\nHi $diverName,\n\nYou have received a new booking request!\n\nClient Name: $userName\nBooking Date: $bookingDate\nNumber of Divers: $paxCount\nTotal Amount: ₱" . number_format($totalAmount, 2) . "\n\nPlease respond within 24 hours.\n\nReview booking: https://diveconnect.site/diver/login_diver.php";

            return $this->mail->send();
        } catch (Exception $e) {
            error_log('New Booking Notification Mailer Error: ' . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Send Booking Approved Notification to User
     */
    public function sendApproved($userEmail, $userName, $diveSite, $bookingDate, $diverName, $remarks = '')
    {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($userEmail, $userName);
            $this->mail->isHTML(true);
            $this->mail->Subject = "✅ Your Dive Booking Has Been Approved!";

            $this->mail->Body = "
            <html>
            <head>
            <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
            <style>
                body {
                    font-family: 'Segoe UI', Arial, sans-serif;
                    background-color: #f0f9f0;
                    margin: 0;
                    padding: 30px;
                }
                .container {
                    max-width: 620px;
                    margin: auto;
                    background: #ffffff;
                    border: 1px solid #c3e6cb;
                    border-radius: 10px;
                    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
                    overflow: hidden;
                }
                .header {
                    text-align: center;
                    padding: 25px 20px;
                    background: linear-gradient(135deg, #10b981, #059669);
                    color: #fff;
                }
                .content {
                    padding: 30px;
                    color: #333;
                    line-height: 1.6;
                }
                .booking-details {
                    background-color: #d1fae5;
                    border: 1px solid #a7f3d0;
                    padding: 20px;
                    margin: 20px 0;
                    border-radius: 8px;
                }
                .detail-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                    padding: 5px 0;
                }
                .detail-label {
                    font-weight: 600;
                    color: #065f46;
                }
                .btn {
                    display: inline-block;
                    background: linear-gradient(135deg, #10b981, #059669);
                    color: white;
                    padding: 14px 28px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-weight: 600;
                    font-size: 16px;
                    transition: 0.3s;
                }
                .btn:hover {
                    background: linear-gradient(135deg, #059669, #047857);
                }
                .footer {
                    background: #ecfdf5;
                    text-align: center;
                    padding: 15px;
                    font-size: 12px;
                    color: #888;
                }
                .remarks {
                    background-color: #eef8ff;
                    border: 1px solid #b9e0ff;
                    padding: 15px;
                    margin: 15px 0;
                    border-radius: 6px;
                    font-style: italic;
                }
            </style>
            </head>
            <body>
            <div class='container'>
                <div class='header'>
                    <h2>🎉 Booking Confirmed!</h2>
                </div>
                <div class='content'>
                    <p>Hi <strong>$userName</strong>,</p>
                    <p>Great news! Your dive booking has been <strong>approved</strong> by your dive master!</p>

                    <div class='booking-details'>
                        <div class='detail-row'>
                            <span class='detail-label'>🏝️ Dive Site:</span>
                            <span>$diveSite</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>📅 Booking Date:</span>
                            <span>$bookingDate</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>🤿 Dive Master:</span>
                            <span>$diverName</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>📧 Contact:</span>
                            <span>$userEmail</span>
                        </div>
                    </div>";

            if (!empty($remarks)) {
                $this->mail->Body .= "
                    <div class='remarks'>
                        <p><strong>💬 Message from $diverName:</strong></p>
                        <p>" . nl2br(htmlspecialchars($remarks)) . "</p>
                    </div>";
            }

            $this->mail->Body .= "
                    <p><strong>📋 What to Prepare:</strong></p>
                    <ul>
                        <li>Bring your diving certification card</li>
                        <li>Arrive 30 minutes before scheduled time</li>
                        <li>Bring swimwear and towel</li>
                        <li>Stay hydrated and avoid alcohol before diving</li>
                    </ul>

                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='https://diveconnect.site/user/user_login.php' class='btn'>
                            📊 View Booking Details
                        </a>
                    </div>

                    <p>If you have any questions, please contact your dive master directly or reply to this email.</p>

                    <p style='margin-top:20px; color:#666;'>We're excited to dive with you! 🌊</p>
                </div>
                <div class='footer'>
                    &copy; " . date('Y') . " DiveConnect. All rights reserved.
                </div>
            </div>
            </body>
            </html>";

            $this->mail->AltBody = "Booking Approved!\n\nHi $userName,\n\nGreat news! Your dive booking has been APPROVED!\n\nDive Site: $diveSite\nBooking Date: $bookingDate\nDive Master: $diverName\n\n" . (!empty($remarks) ? "Message from $diverName: $remarks\n\n" : "") . "What to Prepare:\n• Bring your diving certification card\n• Arrive 30 minutes before scheduled time\n• Bring swimwear and towel\n• Stay hydrated and avoid alcohol before diving\n\nView booking: https://diveconnect.site/user/user_login.php\n\nWe're excited to dive with you!";

            return $this->mail->send();
        } catch (Exception $e) {
            error_log('Booking Approved Mailer Error: ' . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Send Booking Declined Notification to User
     */
    public function sendDeclined($userEmail, $userName, $diveSite, $bookingDate, $diverName, $reason)
    {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($userEmail, $userName);
            $this->mail->isHTML(true);
            $this->mail->Subject = "❌ Your Dive Booking Has Been Declined";

            $this->mail->Body = "
            <html>
            <head>
            <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
            <style>
                body {
                    font-family: 'Segoe UI', Arial, sans-serif;
                    background-color: #fef2f2;
                    margin: 0;
                    padding: 30px;
                }
                .container {
                    max-width: 620px;
                    margin: auto;
                    background: #ffffff;
                    border: 1px solid #fecaca;
                    border-radius: 10px;
                    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
                    overflow: hidden;
                }
                .header {
                    text-align: center;
                    padding: 25px 20px;
                    background: linear-gradient(135deg, #ef4444, #dc2626);
                    color: #fff;
                }
                .content {
                    padding: 30px;
                    color: #333;
                    line-height: 1.6;
                }
                .booking-details {
                    background-color: #fee2e2;
                    border: 1px solid #fecaca;
                    padding: 20px;
                    margin: 20px 0;
                    border-radius: 8px;
                }
                .detail-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                    padding: 5px 0;
                }
                .detail-label {
                    font-weight: 600;
                    color: #991b1b;
                }
                .reason-box {
                    background-color: #fef2f2;
                    border: 1px solid #fecaca;
                    padding: 15px;
                    margin: 15px 0;
                    border-radius: 6px;
                    border-left: 4px solid #ef4444;
                }
                .btn {
                    display: inline-block;
                    background: linear-gradient(135deg, #0077b6, #005f8c);
                    color: white;
                    padding: 14px 28px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-weight: 600;
                    font-size: 16px;
                    transition: 0.3s;
                }
                .btn:hover {
                    background: linear-gradient(135deg, #005f8c, #004a6d);
                }
                .footer {
                    background: #fef2f2;
                    text-align: center;
                    padding: 15px;
                    font-size: 12px;
                    color: #888;
                }
            </style>
            </head>
            <body>
            <div class='container'>
                <div class='header'>
                    <h2>Booking Declined</h2>
                </div>
                <div class='content'>
                    <p>Hi <strong>$userName</strong>,</p>
                    <p>We regret to inform you that your dive booking request has been <strong>declined</strong>.</p>

                    <div class='booking-details'>
                        <div class='detail-row'>
                            <span class='detail-label'>🏝️ Dive Site:</span>
                            <span>$diveSite</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>📅 Booking Date:</span>
                            <span>$bookingDate</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>🤿 Dive Master:</span>
                            <span>$diverName</span>
                        </div>
                    </div>

                    <div class='reason-box'>
                        <p><strong>📋 Reason for Decline:</strong></p>
                        <p>" . nl2br(htmlspecialchars($reason)) . "</p>
                    </div>

                    <p>Don't worry! You can still book with other available dive masters or choose a different date.</p>

                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='https://diveconnect.site/user/dive_masters.php' class='btn'>
                            🔍 Find Another Dive Master
                        </a>
                    </div>

                    <p>If you have any questions, please contact our support team.</p>

                    <p style='margin-top:20px; color:#666;'>We hope to see you diving with us soon!</p>
                </div>
                <div class='footer'>
                    &copy; " . date('Y') . " DiveConnect. All rights reserved.
                </div>
            </div>
            </body>
            </html>";

            $this->mail->AltBody = "Booking Declined\n\nHi $userName,\n\nWe regret to inform you that your dive booking request has been DECLINED.\n\nDive Site: $diveSite\nBooking Date: $bookingDate\nDive Master: $diverName\n\nReason for Decline: $reason\n\nYou can still book with other available dive masters at: https://diveconnect.site/user/dive_masters.php\n\nWe hope to see you diving with us soon!";

            return $this->mail->send();
        } catch (Exception $e) {
            error_log('Booking Declined Mailer Error: ' . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Send Verification Status for Dive Masters
     */
    public function sendVerificationStatus($to, $name, $status)
    {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($to, $name);
            $this->mail->isHTML(true);

            if ($status === 'approved') {
                $this->mail->Subject = "✅ Your Dive Master Account Has Been Approved!";
                $this->mail->Body = "
                <html>
                <head>
                <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
                <style>
                    body {
                        font-family: 'Segoe UI', Arial, sans-serif;
                        background-color: #f0f9f0;
                        margin: 0;
                        padding: 30px;
                    }
                    .container {
                        max-width: 620px;
                        margin: auto;
                        background: #ffffff;
                        border: 1px solid #c3e6cb;
                        border-radius: 10px;
                        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
                        overflow: hidden;
                    }
                    .header {
                        text-align: center;
                        padding: 25px 20px;
                        background: linear-gradient(135deg, #10b981, #059669);
                        color: #fff;
                    }
                    .content {
                        padding: 30px;
                        color: #333;
                        line-height: 1.6;
                    }
                    .highlight {
                        background-color: #d1fae5;
                        border: 1px solid #a7f3d0;
                        padding: 15px;
                        margin: 20px 0;
                        border-radius: 6px;
                    }
                    .btn {
                        display: inline-block;
                        background: #10b981;
                        color: #fff;
                        padding: 12px 24px;
                        border-radius: 6px;
                        text-decoration: none;
                        font-weight: 500;
                        transition: 0.3s;
                    }
                    .btn:hover {
                        background: #059669;
                    }
                    .footer {
                        background: #ecfdf5;
                        text-align: center;
                        padding: 15px;
                        font-size: 12px;
                        color: #888;
                    }
                </style>
                </head>
                <body>
                <div class='container'>
                    <div class='header'>
                        <h2>🎉 Dive Master Account Approved!</h2>
                    </div>
                    <div class='content'>
                        <p>Hi <strong>$name</strong>,</p>
                        <p>Great news! Your Dive Master account has been <strong>approved</strong> by our admin team.</p>

                        <div class='highlight'>
                            <p><strong>🎯 What's Next?</strong></p>
                            <p>• You can now login to your Dive Master dashboard</p>
                            <p>• Set your availability and pricing</p>
                            <p>• Start receiving booking requests</p>
                            <p>• Manage your dive gear rentals</p>
                        </div>

                        <div style='text-align: center; margin: 25px 0;'>
                            <a href='https://diveconnect.site/diver/login_diver.php' class='btn'>
                                🚀 Access Your Dashboard
                            </a>
                        </div>

                        <p>Welcome to our community of professional dive masters! We're excited to have you onboard.</p>

                        <p style='margin-top:20px; color:#666;'>This is an automated email. Please do not reply.</p>
                    </div>
                    <div class='footer'>
                        &copy; " . date('Y') . " DiveConnect. All rights reserved.
                    </div>
                </div>
                </body>
                </html>";

                $this->mail->AltBody = "Hi $name,\n\nGreat news! Your Dive Master account has been APPROVED by our admin team.\n\nWhat's Next?\n• You can now login to your Dive Master dashboard\n• Set your availability and pricing\n• Start receiving booking requests\n• Manage your dive gear rentals\n\nLogin here: https://diveconnect.site/diver/login_diver.php\n\nWelcome to our community of professional dive masters!";
            } else {
                $this->mail->Subject = "❌ Your Dive Master Application Status";
                $this->mail->Body = "
                <html>
                <head>
                <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
                <style>
                    body {
                        font-family: 'Segoe UI', Arial, sans-serif;
                        background-color: #fef2f2;
                        margin: 0;
                        padding: 30px;
                    }
                    .container {
                        max-width: 620px;
                        margin: auto;
                        background: #ffffff;
                        border: 1px solid #fecaca;
                        border-radius: 10px;
                        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
                        overflow: hidden;
                    }
                    .header {
                        text-align: center;
                        padding: 25px 20px;
                        background: linear-gradient(135deg, #ef4444, #dc2626);
                        color: #fff;
                    }
                    .content {
                        padding: 30px;
                        color: #333;
                        line-height: 1.6;
                    }
                    .highlight {
                        background-color: #fee2e2;
                        border: 1px solid #fecaca;
                        padding: 15px;
                        margin: 20px 0;
                        border-radius: 6px;
                    }
                    .btn {
                        display: inline-block;
                        background: #ef4444;
                        color: #fff;
                        padding: 12px 24px;
                        border-radius: 6px;
                        text-decoration: none;
                        font-weight: 500;
                        transition: 0.3s;
                    }
                    .btn:hover {
                        background: #dc2626;
                    }
                    .footer {
                        background: #fef2f2;
                        text-align: center;
                        padding: 15px;
                        font-size: 12px;
                        color: #888;
                    }
                </style>
                </head>
                <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Dive Master Application Update</h2>
                    </div>
                    <div class='content'>
                        <p>Hi <strong>$name</strong>,</p>
                        <p>We regret to inform you that your Dive Master application has been <strong>rejected</strong>.</p>

                        <div class='highlight'>
                            <p><strong>📋 Possible Reasons:</strong></p>
                            <p>• Incomplete documentation</p>
                            <p>• Invalid certification details</p>
                            <p>• Verification issues with provided credentials</p>
                            <p>• Other administrative reasons</p>
                        </div>

                        <p>If you believe this is an error or would like to appeal this decision, please contact our support team for assistance.</p>

                        <div style='text-align: center; margin: 25px 0;'>
                            <a href='mailto:diveconnect25@gmail.com' class='btn'>
                                📞 Contact Support
                            </a>
                        </div>

                        <p style='margin-top:20px; color:#666;'>This is an automated email. Please do not reply.</p>
                    </div>
                    <div class='footer'>
                        &copy; " . date('Y') . " DiveConnect. All rights reserved.
                    </div>
                </div>
                </body>
                </html>";

                $this->mail->AltBody = "Hi $name,\n\nWe regret to inform you that your Dive Master application has been REJECTED.\n\nPossible Reasons:\n• Incomplete documentation\n• Invalid certification details\n• Verification issues with provided credentials\n• Other administrative reasons\n\nIf you believe this is an error, please contact our support team: diveconnect25@gmail.com";
            }

            return $this->mail->send();
        } catch (Exception $e) {
            error_log('Dive Master Verification Mailer Error: ' . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Send User Diver Verification Status
     */
    public function sendUserVerificationStatus($to, $name, $status)
    {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($to, $name);
            $this->mail->isHTML(true);

            if ($status === 1) {
                $this->mail->Subject = "✅ Your User Diver Account Has Been Approved!";
                $this->mail->Body = "
                <html>
                <head>
                <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
                <style>
                    body {
                        font-family: 'Segoe UI', Arial, sans-serif;
                        background-color: #f0f9f0;
                        margin: 0;
                        padding: 30px;
                    }
                    .container {
                        max-width: 620px;
                        margin: auto;
                        background: #ffffff;
                        border: 1px solid #c3e6cb;
                        border-radius: 10px;
                        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
                        overflow: hidden;
                    }
                    .header {
                        text-align: center;
                        padding: 25px 20px;
                        background: linear-gradient(135deg, #10b981, #059669);
                        color: #fff;
                    }
                    .content {
                        padding: 30px;
                        color: #333;
                        line-height: 1.6;
                    }
                    .highlight {
                        background-color: #d1fae5;
                        border: 1px solid #a7f3d0;
                        padding: 15px;
                        margin: 20px 0;
                        border-radius: 6px;
                    }
                    .btn {
                        display: inline-block;
                        background: #10b981;
                        color: #fff;
                        padding: 12px 24px;
                        border-radius: 6px;
                        text-decoration: none;
                        font-weight: 500;
                        transition: 0.3s;
                    }
                    .btn:hover {
                        background: #059669;
                    }
                    .footer {
                        background: #ecfdf5;
                        text-align: center;
                        padding: 15px;
                        font-size: 12px;
                        color: #888;
                    }
                </style>
                </head>
                <body>
                <div class='container'>
                    <div class='header'>
                        <h2>🎉 User Diver Account Approved!</h2>
                    </div>
                    <div class='content'>
                        <p>Hi <strong>$name</strong>,</p>
                        <p>Great news! Your User Diver account has been <strong>approved</strong> by our admin team.</p>

                        <div class='highlight'>
                            <p><strong>🎯 What's Next?</strong></p>
                            <p>• You can now login to your account</p>
                            <p>• Explore diving destinations</p>
                            <p>• Book with professional dive masters</p>
                            <p>• Join our diving community</p>
                        </div>

                        <div style='text-align: center; margin: 25px 0;'>
                            <a href='https://diveconnect.site/user/user_login.php' class='btn'>
                                🚀 Login to Your Account
                            </a>
                        </div>

                        <p>We're excited to have you onboard and can't wait to help you explore amazing underwater adventures!</p>

                        <p style='margin-top:20px; color:#666;'>This is an automated email. Please do not reply.</p>
                    </div>
                    <div class='footer'>
                        &copy; " . date('Y') . " DiveConnect. All rights reserved.
                    </div>
                </div>
                </body>
                </html>";

                $this->mail->AltBody = "Hi $name,\n\nGreat news! Your User Diver account has been APPROVED by our admin team.\n\nWhat's Next?\n• You can now login to your account\n• Explore diving destinations\n• Book with professional dive masters\n• Join our diving community\n\nLogin here: https://diveconnect.site/user/user_login.php\n\nWe're excited to have you onboard!";
            } else {
                $this->mail->Subject = "❌ Your User Diver Account Application Status";
                $this->mail->Body = "
                <html>
                <head>
                <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
                <style>
                    body {
                        font-family: 'Segoe UI', Arial, sans-serif;
                        background-color: #fef2f2;
                        margin: 0;
                        padding: 30px;
                    }
                    .container {
                        max-width: 620px;
                        margin: auto;
                        background: #ffffff;
                        border: 1px solid #fecaca;
                        border-radius: 10px;
                        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
                        overflow: hidden;
                    }
                    .header {
                        text-align: center;
                        padding: 25px 20px;
                        background: linear-gradient(135deg, #ef4444, #dc2626);
                        color: #fff;
                    }
                    .content {
                        padding: 30px;
                        color: #333;
                        line-height: 1.6;
                    }
                    .highlight {
                        background-color: #fee2e2;
                        border: 1px solid #fecaca;
                        padding: 15px;
                        margin: 20px 0;
                        border-radius: 6px;
                    }
                    .btn {
                        display: inline-block;
                        background: #ef4444;
                        color: #fff;
                        padding: 12px 24px;
                        border-radius: 6px;
                        text-decoration: none;
                        font-weight: 500;
                        transition: 0.3s;
                    }
                    .btn:hover {
                        background: #dc2626;
                    }
                    .footer {
                        background: #fef2f2;
                        text-align: center;
                        padding: 15px;
                        font-size: 12px;
                        color: #888;
                    }
                </style>
                </head>
                <body>
                <div class='container'>
                    <div class='header'>
                        <h2>User Diver Application Update</h2>
                    </div>
                    <div class='content'>
                        <p>Hi <strong>$name</strong>,</p>
                        <p>We regret to inform you that your User Diver account application has been <strong>rejected</strong>.</p>

                        <div class='highlight'>
                            <p><strong>📋 Possible Reasons:</strong></p>
                            <p>• Incomplete documentation</p>
                            <p>• Invalid certification details</p>
                            <p>• Verification issues with provided credentials</p>
                            <p>• Other administrative reasons</p>
                        </div>

                        <p>If you believe this is an error or would like to appeal this decision, please contact our support team for assistance.</p>

                        <div style='text-align: center; margin: 25px 0;'>
                            <a href='mailto:diveconnect25@gmail.com' class='btn'>
                                📞 Contact Support
                            </a>
                        </div>

                        <p style='margin-top:20px; color:#666;'>This is an automated email. Please do not reply.</p>
                    </div>
                    <div class='footer'>
                        &copy; " . date('Y') . " DiveConnect. All rights reserved.
                    </div>
                </div>
                </body>
                </html>";

                $this->mail->AltBody = "Hi $name,\n\nWe regret to inform you that your User Diver account application has been REJECTED.\n\nPossible Reasons:\n• Incomplete documentation\n• Invalid certification details\n• Verification issues with provided credentials\n• Other administrative reasons\n\nIf you believe this is an error, please contact our support team: diveconnect25@gmail.com";
            }

            return $this->mail->send();
        } catch (Exception $e) {
            error_log('User Diver Verification Mailer Error: ' . $this->mail->ErrorInfo);
            return false;
        }
    }
}