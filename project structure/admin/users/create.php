<?php
include '../../config/db.php';
include '../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // Validation
    if (empty($full_name) || empty($email) || empty($_POST['password'])) {
        $error = "Please fill all fields";
    } else {
        $sql = "INSERT INTO users(full_name, email, password_hash, role)
                VALUES (?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssss", $full_name, $email, $password, $role);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: index.php");
            exit;
        } else {
            $error = "Failed to create user";
        }
    }
});
            exit;
?>

<?php include '../../includes/header.php'; ?>

<div class="container mt-4">
    <h2>Create User</h2>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label>Full Name</label>
            <input type="text" name="full_name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Role</label>
            <select name="role" class="form-control">
                <option value="admin">Admin</option>
                <option value="student">Student</option>
                 </select>
        </div>

        <button type="submit" class="btn btn-success">Create</button>
        <a href="index.php" class="btn btn-secondary">Back</a>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>