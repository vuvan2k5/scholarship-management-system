<?php
require_once "../../config/database.php";

$sql = "SELECT * FROM reports ORDER BY id DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Management</title>

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

        .report-title{
            font-weight:600;
            color:#0d6efd;
        }

        .content-box{
            max-width:350px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }

        .date-badge{
            background:#6c757d;
            color:white;
            padding:6px 12px;
            border-radius:20px;
            font-size:13px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="container-box">

        <h2>Reports Management</h2>

        <a href="create.php" class="btn btn-primary mb-3">
            Add Report
        </a>

        <table class="table table-bordered table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Content</th>
                    <th>Created At</th>
                    <th width="180">Action</th>
                </tr>
            </thead>

            <tbody>

                <?php while ($row = mysqli_fetch_assoc($result)) { ?>

                    <tr>

                        <td><?= $row['id'] ?></td>

                        <td>
                            <span class="report-title">
                                <?= $row['title'] ?>
                            </span>
                        </td>

                        <td>
                            <div class="content-box">
                                <?= $row['content'] ?>
                            </div>
                        </td>

                        <td>
                            <span class="date-badge">
                                <?= $row['created_at'] ?>
                            </span>
                        </td>

                        <td>

                            <a href="edit.php?id=<?= $row['id'] ?>"
                               class="btn btn-success btn-sm">
                               Edit
                            </a>

                            <a href="delete.php?id=<?= $row['id'] ?>"
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Delete this report?')">
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