<?php
require_once '../sessions.php';
include('../db.php');
require_role(['worker', 'admin']);
$loginBg = '../cover/cover45.jpg';

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header('location: ../index.php');
    exit();
}

$error = '';
$success = '';
$user_type = $_SESSION['user_type'];
$added_by_user_id = $_SESSION['user_id'];
$date_added = date('Y-m-d');

// Handle the form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name     = trim($_POST['name']);
    $price    = trim($_POST['price']);
    $code     = trim($_POST['code']);
    $class    = !empty($_POST['class']) ? trim($_POST['class']) : '';
    $color    = !empty($_POST['color']) ? trim($_POST['color']) : null;
    $comment  = !empty($_POST['comment']) ? trim($_POST['comment']) : null;
    $quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 0;

    if (empty($name) || empty($price) || empty($code) || empty($quantity)) {
        $error = "Name, price, code and quantity are required.";
    } elseif (!ctype_digit($code) || strlen($code) > 20) {
        $error = "Code must be a number with a maximum of 20 digits.";
    } else {
        // check duplicate code in products
        $stmt = $conn->prepare("SELECT 1 FROM products WHERE Code = ?");
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $result = $stmt->get_result();
        $net_price = (float)$price * 0.9;

        if ($result->num_rows > 0) {
            $error = "Code must be unique. This code is already in use.";
            $stmt->close();
        } else {
            $stmt->close();

            if ($user_type === 'admin') {
                $query = "INSERT INTO products (Name, Price, net_price, Code, Class, Color, Comment, added_by_user_id, date_added, quantity)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            } else {
                $query = "INSERT INTO product_check (name, price, net_price, code, class, color, comment, added_by, created_at, quantity)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            }

            $stmt = $conn->prepare($query);
            $stmt->bind_param("sddssssisi", $name, $price, $net_price, $code, $class, $color, $comment, $added_by_user_id, $date_added, $quantity);

            if ($stmt->execute()) {
                $product_id = $stmt->insert_id;

                // Handle image uploads
                if (isset($_FILES['product_images']) && isset($_FILES['product_images']['name']) && $_FILES['product_images']['name'][0] !== '') {
                    $image_count = count($_FILES['product_images']['name']);
                    $upload_dir = "../uploads/";

                    for ($i = 0; $i < $image_count; $i++) {
                        $image_name     = $_FILES['product_images']['name'][$i];
                        $image_tmp_name = $_FILES['product_images']['tmp_name'][$i];
                        $image_error    = $_FILES['product_images']['error'][$i];

                        if ($image_error === UPLOAD_ERR_OK && is_uploaded_file($image_tmp_name)) {
                            $safeBase  = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($image_name));
                            $image_path = $upload_dir . uniqid('', true) . '_' . $safeBase;

                            if (move_uploaded_file($image_tmp_name, $image_path)) {
                                // store as public URL path
                                $db_image_path = 'uploads/' . basename($image_path);

                                if ($user_type === 'admin') {
                                    $sql_image = "INSERT INTO product_images (product_id, image_path) VALUES (?, ?)";
                                    $stmt_image = $conn->prepare($sql_image);
                                    $stmt_image->bind_param('is', $product_id, $db_image_path);
                                    $stmt_image->execute();
                                    $stmt_image->close();
                                } else {
                                    // for product_check keep one path (last uploaded wins)
                                    $sql_image = "UPDATE product_check SET image_path = ? WHERE id = ?";
                                    $stmt_image = $conn->prepare($sql_image);
                                    $stmt_image->bind_param('si', $db_image_path, $product_id);
                                    $stmt_image->execute();
                                    $stmt_image->close();
                                }
                            }
                        }
                    }
                }

                $success = ($user_type === 'admin')
                    ? "Product added successfully!"
                    : "Product submitted for admin approval.";
            } else {
                $error = "Error adding product: " . $stmt->error;
            }

            $stmt->close();
        }
        // IMPORTANT: removed the unconditional $success assignment that caused green alert on errors
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Product</title>
    <link rel="icon" href="../merkaza.jpeg" type="image/jpeg">
    <link rel="stylesheet" type="text/css" href="add.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


</head>

<body class="with-cover scrollable"
      style="--cover-image: url('<?= htmlspecialchars($loginBg) ?>');">

  <div class="wrapper d-flex flex-column min-vh-100">

    <?php include('../navbar.php'); ?>
    <?php include '../header.php'; ?>

    <div class="flex-grow-1">
      <div class="container surface-none add-shell">
        <div class="add-title">Add New Product</div>

        <?php if ($error): ?>
          <div class="alert alert-danger" role="alert" style="max-width:860px;margin:0 auto 12px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="alert alert-success" role="alert" style="max-width:860px;margin:0 auto 12px;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form action="add.php" method="POST" enctype="multipart/form-data" class="add-card">
          <div class="form-grid">

            <div class="field">
              <label for="name" class="required">Product Name (required):</label>
              <input type="text" name="name" id="name" required>
            </div>

            <div class="two-up">
              <div class="field">
                <label for="price" class="required">Price (required):</label>
                <input type="number" step="0.01" name="price" id="price" required>
              </div>
              <div class="field">
                <label for="quantity" class="required">Quantity (required):</label>
                <input type="number" name="quantity" id="quantity" min="1" required>
              </div>
            </div>

            <div class="field">
              <label for="code" class="required">Code (required, numbers only, max 20 digits):</label>
              <input type="text" name="code" id="code" maxlength="20"
                     pattern="\d{1,20}" title="Code must contain only numbers (up to 20 digits)" required>
            </div>

            <div class="two-up">
              <div class="field">
                <label for="class" class="required">Product Class:</label>
                <select name="class" id="class" required>
                  <option value="" disabled selected>Select a class</option>
                  <option value="kle bait">kle bait</option>
                  <option value="shtea">shtea</option>
                  <option value="shtea 7arefa">shtea 7arefa</option>
                  <option value="m2fea">m2fea</option>
                  <option value="meat">meat</option>
                </select>
              </div>

              <div class="field">
                <label for="color">Product Color:</label>
                <select id="color" name="color" onchange="updateColor(this)">
                  <option value="">Select a color</option>
                  <option value="red">Red</option>
                  <option value="blue">Blue</option>
                  <option value="green">Green</option>
                </select>
              </div>
            </div>

            <div class="field">
              <label for="comment">Comments/Tags (Optional):</label>
              <textarea id="comment" name="comment" placeholder="Add keywords or comments..."></textarea>
            </div>

            <!-- Upload -->
            <div class="field">
              <input type="hidden" name="product_id" value="<?php echo isset($product_id)?$product_id:''; ?>">
              <label>Upload Images:</label>

              <div class="droparea" id="droparea">
                <div>Drag & drop images here, or click to choose</div>
                <input type="file" name="product_images[]" id="product_images" multiple hidden>
                <button type="button" class="btn-pill" id="chooseBtn" style="margin-top:10px;">Choose files</button>
                <div id="fileHint" style="margin-top:6px; font-size:.9rem; opacity:.8;"></div>
              </div>

              <div class="thumbs" id="thumbs"></div>
            </div>

          </div>

          <button type="submit" class="btn-pill btn-wide">Add Product</button>
        </form>
      </div>
    </div>

    <?php include '../footer/footer.php'; ?>
  </div>

  <script>
    function updateColor(selectElement) {
      selectElement.style.backgroundColor = selectElement.value;
    }

    const drop = document.getElementById('droparea');
    const input = document.getElementById('product_images');
    const thumbs = document.getElementById('thumbs');
    const fileHint = document.getElementById('fileHint');
    const chooseBtn = document.getElementById('chooseBtn');

    function handleFiles(files){
      thumbs.innerHTML = '';
      if(!files || !files.length){ fileHint.textContent = ''; return; }
      fileHint.textContent = files.length + ' file' + (files.length>1?'s':'') + ' selected';
      Array.from(files).forEach(f=>{
        if(!f.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = e=>{
          const img = document.createElement('img');
          img.src = e.target.result;
          thumbs.appendChild(img);
        };
        reader.readAsDataURL(f);
      });
    }

    // Prevent double-open: stop bubbling from the button
    chooseBtn.addEventListener('click', (e)=>{ e.stopPropagation(); input.click(); });

    drop.addEventListener('click', ()=> input.click());
    drop.addEventListener('dragover', e=>{ e.preventDefault(); drop.classList.add('dragover'); });
    drop.addEventListener('dragleave', ()=> drop.classList.remove('dragover'));
    drop.addEventListener('drop', e=>{
      e.preventDefault(); drop.classList.remove('dragover');
      input.files = e.dataTransfer.files;
      handleFiles(input.files);
    });
    input.addEventListener('change', ()=> handleFiles(input.files));
  </script>
</body>
</html>
