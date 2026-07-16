<?php
// Get the project root directory using __DIR__
$projectRoot = dirname(__DIR__);

require_once dirname(__DIR__) . '/config/setPath.php';

 
require_once __DIR__ . '/Database.php';

class Contact {
    private $db;
    private $recipientEmail = 'yunus@yunus.email';
    private $useMailer = false;

    // SMTP settings
    private $smtpHost = 'yunusemrevurgun.com';
    private $smtpPort = 465;
    private $smtpUsername = 'yunus@yunusemrevurgun.com';
    private $smtpPassword = null; // Must be configured via config/mail.php

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createTableIfNotExists();
        
        // Check if PHPMailer is available
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
            $this->useMailer = true;
            
            // Load mail configuration if exists
            if (file_exists(__DIR__ . '/../config/mail.php')) {
                $config = require __DIR__ . '/../config/mail.php';
                $this->smtpHost = $config['smtp_host'];
                $this->smtpPort = $config['smtp_port'];
                $this->smtpUsername = $config['smtp_username'];
                $this->smtpPassword = $config['smtp_password'];
            }
        }
    }

    private function createTableIfNotExists() {
        $query = "CREATE TABLE IF NOT EXISTS contact_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('new', 'read', 'replied') DEFAULT 'new'
        )";
        
        $this->db->exec($query);
    }

    public function sendEmail($data) {
        if (!$this->validateEmail($data['email'])) {
            throw new Exception('Invalid email address');
        }

        // Store in database first
        $query = "INSERT INTO contact_messages (name, email, subject, message) 
                 VALUES (:name, :email, :subject, :message)";
        
        $stmt = $this->db->prepare($query);
        $stored = $stmt->execute([
            ':name' => $data['name'],
            ':email' => $data['email'],
            ':subject' => $data['subject'],
            ':message' => $data['message']
        ]);

        // Try sending via PHP mail() as primary (works on Namecheap shared hosting)
        try {
            $to = $this->recipientEmail;
            $subject = '[Website Contact] ' . $data['subject'];
            $headers = "From: " . $data['name'] . " <" . $data['email'] . ">\r\n";
            $headers .= "Reply-To: " . $data['email'] . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            
            $body = "
                <h2>New Contact Form Submission</h2>
                <p><strong>From:</strong> " . htmlspecialchars($data['name']) . " (" . htmlspecialchars($data['email']) . ")</p>
                <p><strong>Subject:</strong> " . htmlspecialchars($data['subject']) . "</p>
                <p><strong>Message:</strong></p>
                <p>" . nl2br(htmlspecialchars($data['message'])) . "</p>
            ";
            
            @mail($to, $subject, $body, $headers);
        } catch (Exception $e) {
            error_log("mail() Error: " . $e->getMessage());
        }

        // If PHPMailer is not available, return database result
        if (!$this->useMailer) {
            return $stored;
        }

        // Also try sending via PHPMailer if available
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpUsername;
            $mail->Password = $this->smtpPassword;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = $this->smtpPort;

            // Recipients
            $mail->setFrom($this->smtpUsername, 'Website Contact Form');
            $mail->addAddress($this->recipientEmail);
            $mail->addReplyTo($data['email'], $data['name']);

            // Content
            $mail->isHTML(true);
            $mail->Subject = '[Website Contact] ' . $data['subject'];
            
            $mail->Body = "
                <h2>New Contact Form Submission</h2>
                <p><strong>From:</strong> " . htmlspecialchars($data['name']) . " (" . htmlspecialchars($data['email']) . ")</p>
                <p><strong>Subject:</strong> " . htmlspecialchars($data['subject']) . "</p>
                <p><strong>Message:</strong></p>
                <p>" . nl2br(htmlspecialchars($data['message'])) . "</p>
            ";

            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $mail->Body));

            return $mail->send() && $stored;
        } catch (Exception $e) {
            error_log("Mailer Error: " . $e->getMessage());
            return $stored; // Return true if at least the database storage worked
        }
    }

    private function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) && 
               preg_match('/@.+\./', $email);
    }

    public function getAllMessages() {
        $query = "SELECT * FROM contact_messages ORDER BY created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markAsRead($id) {
        $query = "UPDATE contact_messages SET status = 'read' WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([':id' => $id]);
    }

    public function markAsReplied($id) {
        $query = "UPDATE contact_messages SET status = 'replied' WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([':id' => $id]);
    }
}