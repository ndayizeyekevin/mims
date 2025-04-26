<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #1a1a1a;
            color: #f1f1f1;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .container {
            text-align: center;
            background-color: #2a2a2a;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }
        h1 {
            margin-bottom: 20px;
            font-size: 2rem;
            color: #4CAF50;
        }
        input[type="email"] {
            padding: 12px;
            width: 100%;
            max-width: 400px;
            border: 1px solid #444;
            border-radius: 5px;
            margin-bottom: 20px;
            background-color: #333;
            color: #f1f1f1;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        input[type="email"]:focus {
            border-color: #4CAF50;
            outline: none;
        }
        button {
            padding: 12px 30px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s, transform 0.2s;
        }
        button:hover {
            background-color: #45a049;
            transform: translateY(-2px);
        }
        button:active {
            transform: translateY(0);
        }
    </style>
    
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="./images/logo-removebg-preview.png">
    
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>Password Reset</h1>

        <?php
        require '../include/connection.php'; // Include your connection

        if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['email'])) {
            $email = $_POST['email'];

            // SQL queries to check for the user in different tables
            $sql_users = "SELECT * FROM users WHERE email = :email";
            $sql_pharmacy_users = "SELECT * FROM pharmacy_users WHERE email = :email";
            $sql_insurance_users = "SELECT * FROM insurance_users WHERE email = :email";

            // Function to check for the user and return the table name if found
            function checkUser($connection, $sql, $email, $tableName) {
                $stmt = $connection->prepare($sql);
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt->execute();
                return $stmt->rowCount() > 0 ? $tableName : false;
            }

            // Initialize the table variable
            $table = null;

            // Check in different tables and store the table name if email is found
            if ($table = checkUser($connection, $sql_users, $email, 'users')) {
                // Email found in users table
            } elseif ($table = checkUser($connection, $sql_pharmacy_users, $email, 'pharmacy_users')) {
                // Email found in pharmacy_users table
            } elseif ($table = checkUser($connection, $sql_insurance_users, $email, 'insurance_users')) {
                // Email found in insurance_users table
            }

            if ($table) {
                // Generate the reset key
                $resetKey = bin2hex(random_bytes(10)); // Generate a random key of length 20 (10 bytes)

                // Store the reset key in the found table (assuming all tables have a reset_key field)
                $stmt = $connection->prepare("UPDATE $table SET reset_key = :reset_key WHERE email = :email");
                $stmt->bindParam(':reset_key', $resetKey);
                $stmt->bindParam(':email', $email);
                $stmt->execute();

                // Display success message in a SweetAlert2 dialog
                echo "
                <script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'User exists in $table, an email with the reset key is being sent.',
                        confirmButtonText: 'OK'
                    });

                    (function() {
                        emailjs.init('Enter you EmailJS User ID'); // Replace with your EmailJS User ID
                    })();

                    async function sendKey() {
                        const message = `You requested to reset your password. Here is your reset key: \${'$resetKey'}. Please use this to reset your password.
                        Reset your password using the following link: http://127.0.0.1/mims/password/changepassword/index.php?key=\${'$resetKey'}&email=${email}
                        If you did not request a password reset, please ignore this email.`;

                        const templateParams = {
                            to_email: '$email', 
                            message: message,
                            reset_key: '$resetKey'
                        };

                        try {
                            const response = await emailjs.send('service_ID', 'template_ID', templateParams); //Replace with your credentials from EmailJS 
                            Swal.fire({
                                icon: 'success',
                                title: 'Email Sent',
                                text: 'An email with the reset key has been sent successfully.',
                                confirmButtonText: 'OK'
                            });
                        } catch (error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Failed to send the email. Please try again.',
                                confirmButtonText: 'OK'
                            });
                        }
                    }

                    sendKey();
                </script>";
            } else {
                // Display error message if user does not exist
                echo "
                <script>
                    Swal.fire({
                        icon: 'error',
                        title: 'User Not Found',
                        text: 'The email does not exist in our records.',
                        confirmButtonText: 'OK'
                    });
                </script>";
            }
        }
        ?>

        <!-- The form where the email is entered -->
        <form action="" method="post" id="myForm">
            <input type="email" id="email" name="email" placeholder="Enter your email" required>
            <button type="submit">Send Login Link</button>
        </form>
        <a href="../index.html" class='btn btn-primary py-3 px-3'><i class="bi bi-house"></i> Homepage </a>
    </div>
</body>
</html>
