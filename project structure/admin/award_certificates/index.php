<?php
require_once "../../config/database.php";

$sql = "SELECT * FROM award_certificates ORDER BY id DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Award Certificates Management</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body{
            background-color:#f5f6fa;
        }

        .container-box{
            background:white;
            padding:30px;
            border-radius:12px;
            margin-top:30px;
            box-shadow:0 0 10px rgba(0,0,0,0.1);
        }

        h2{
            font-weight:bold;
            margin-bottom:20px;
        }

        table th{
            background:#f1f1f1;
        }

        .certificate-badge{
            background:#0d6efd;
            color:white;
            padding:6px 12px;
            border-radius:20px;
            font-size:13px;
        }

        .file-link{
            text-decoration:none;
            font-weight:500;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="container-box">

        <h2>Award Certificates Management</h2>

        <a href="create.php" class="btn btn-primary mb-3">
            Add Award Certificate
        </a>

        <table class="table table-bordered table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Application ID</th>
                    <th>Certificate Code</th>
                    <th>Issued At</th>
                    <th>File Path</th>
                    <th width="180">Action</th>
                </tr>
            </thead>

            <tbody>

                <?php while ($row = mysqli_fetch_assoc($result)) { ?>

                    <tr>

                        <td><?= $row['id'] ?></td>

                        <td><?= $row['application_id'] ?></td>

                        <td>
                            <span class="certificate-badge">
                                <?= $row['certificate_code'] ?>
                            </span>
                        </td>

                        <td>
                            <?= $row['issued_at'] ?>
                        </td>

                        <td>
                            <a href="<?= $row['file_path'] ?>" 
                               target="_blank"
                               class="file-link btn btn-outline-secondary btn-sm">
                               View File
                            </a>
                        </td>

                        <td>

                            <a href="edit.php?id=<?= $row['id'] ?>"
                               class="btn btn-success btn-sm">
                               Edit
                            </a>

                            <a href="delete.php?id=<?= $row['id'] ?>"
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Delete this certificate?')">
                               Delete
                            </a>

                        </td>

                    </tr>

                <?php } ?>

            </tbody>

        </table>

    </div>
</div>

</body>
</html>