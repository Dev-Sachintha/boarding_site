<?php
// /ragging-complaint-site/generate_hash.php

// --- IMPORTANT ---
// SET THE DESIRED ADMIN PASSWORD HERE:
// Replace 'your_strong_password' with the actual password you want to set for the admin account.
$plainPassword = 'PriSachi2002*/'; // <--- !!! CHANGE THIS VALUE !!!
// --- END IMPORTANT ---


// Generate the secure hash using PHP's recommended default algorithm (currently bcrypt)
$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

// --- Output ---
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Password Hash Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 2rem;
            font-family: sans-serif;
        }

        .hash-output {
            font-family: monospace;
            word-wrap: break-word;
            background-color: #e9ecef;
            padding: 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
        }

        .warning {
            color: #dc3545;
            /* Bootstrap danger color */
            font-weight: bold;
            margin-top: 1rem;
            border: 2px solid #dc3545;
            padding: 1rem;
            background-color: #f8d7da;
            /* Bootstrap danger background */
            border-radius: 0.25rem;
        }

        .verification-ok {
            color: green;
            font-weight: bold;
        }

        .verification-failed {
            color: red;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Admin Password Hash Generator</h1>
        <hr>

        <p><strong>Password you set in the script:</strong> <?php echo htmlspecialchars($plainPassword); ?></p>

        <?php if ($hashedPassword === false): ?>
            <div class="alert alert-danger">Error generating hash. Check PHP version and configuration.</div>
        <?php else: ?>
            <p><strong>Generated Hash (Copy this entire value):</strong></p>
            <div class="hash-output">
                <?php echo htmlspecialchars($hashedPassword); ?>
            </div>

            <p class="mt-3"><strong>Internal Verification Check:</strong>
                <?php
                // Verify the hash against the original password
                if (password_verify($plainPassword, $hashedPassword)) {
                    echo '<span class="verification-ok">OK (Hash matches password)</span>';
                } else {
                    echo '<span class="verification-failed">FAILED (Something went wrong!)</span>';
                }
                ?>
            </p>

            <hr>
            <h2>Next Steps:</h2>
            <ol>
                <li>Copy the full hash string displayed above.</li>
                <li>Go to your database tool (e.g., phpMyAdmin).</li>
                <li>Open the `ragging_complaints_db` database.</li>
                <li>Browse the `admins` table.</li>
                <li>Edit the row for the admin user you want to set the password for.</li>
                <li>Paste the copied hash into the `password_hash` column.</li>
                <li>Save the changes in the database.</li>
                <li><strong style="color:red;">DELETE THIS FILE (`generate_hash.php`) from your server!</strong></li>
            </ol>

        <?php endif; ?>

        <div class="warning">
            SECURITY WARNING: This script displays potentially sensitive information (the plain password you entered in the code). DELETE THIS FILE (`generate_hash.php`) FROM YOUR SERVER IMMEDIATELY AFTER YOU HAVE COPIED THE HASH AND UPDATED YOUR DATABASE!
        </div>
    </div>
</body>

</html>