<?php
// login.php - Admin Login Portal
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// If already logged in, redirect to dashboard
check_logged_in();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                // Store session details
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_username'] = $admin['username'];
                
                header("Location: dashboard.php");
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="container my-5 py-5 d-flex justify-content-center align-items-center" style="min-height: 70vh;">
    <div class="row w-100 justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 rounded-4 shadow-lg bg-white p-4">
                <div class="text-center mb-4">
                    <div class="bg-bakery-sand d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 70px; height: 70px;">
                        <i class="fa-solid fa-user-shield fa-2x text-bakery-brown"></i>
                    </div>
                    <h2 class="font-heading text-bakery-brown">Admin Secure Access</h2>
                    <p class="text-muted small">Enter your staff credentials to enter management panel</p>
                </div>

                <!-- Error Messages -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <!-- Username -->
                    <div class="mb-3">
                        <label for="usernameInput" class="form-label fw-semibold text-secondary">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-user text-muted"></i></span>
                            <input type="text" name="username" id="usernameInput" class="form-control bg-light border-start-0 py-2.5" placeholder="e.g. admin" required autofocus>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="mb-4">
                        <label for="passwordInput" class="form-label fw-semibold text-secondary">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-key text-muted"></i></span>
                            <input type="password" name="password" id="passwordInput" class="form-control bg-light border-start-0 py-2.5" placeholder="Enter password" required>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-bakery-dark w-100 py-2.5 fs-6 shadow-sm mb-3">
                        <i class="fa-solid fa-right-to-bracket me-2"></i> Log In
                    </button>
                    
                    <div class="text-center">
                        <a href="../index.php" class="text-muted small text-decoration-none"><i class="fa-solid fa-arrow-left me-1"></i> Return to Shop Catalog</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
