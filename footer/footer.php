<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/Merkaza-Almost-Done/public_html/footer/footer.css">

<?php
// Figure out the display name (works with your sessions.php setup)
$displayName = '';
if (!empty($username)) {
    $displayName = $username;
} elseif (!empty($_SESSION['username'])) {
    $displayName = $_SESSION['username'];
} elseif (!empty($_SESSION['name'])) {
    $displayName = $_SESSION['name'];
}
?>

<footer class="footer">

  <!-- ✅ Combined scrolling: image + text + image -->
  <div class="image-scroll-left">
    <div class="image-track">
      <?php
        $imageFolder = __DIR__ . '/footer_images';
        $imageUrlPath = '/Merkaza-Almost-Done/public_html/footer/footer_images';
        $images = array_values(array_diff(scandir($imageFolder), ['.', '..']));

        if (count($images) >= 2) {
          echo '<div class="image-item"><img src="' . $imageUrlPath . '/' . htmlspecialchars($images[0]) . '" alt="Left Logo"></div>';
          echo '<div class="text-scroll-left"><p>🔥 Welcome to Merkaza — Big Sales this weekend only! 🔥</p></div>';
          echo '<div class="image-item"><img src="' . $imageUrlPath . '/' . htmlspecialchars($images[1]) . '" alt="Right Logo"></div>';
        }
      ?>
    </div>
  </div>

  <!-- ✅ Social links -->
  <div class="footer__redes">
    <ul class="footer__redes-wrapper">
      <li><a href="#" class="footer__link"><i class="fab fa-facebook-f"></i> Facebook</a></li>
      <li><a href="#" class="footer__link"><i class="fab fa-twitter"></i> Twitter</a></li>
      <li><a href="#" class="footer__link"><i class="fab fa-instagram"></i> Instagram</a></li>
      <li><a href="#" class="footer__link"><i class="fab fa-youtube"></i> YouTube</a></li>
    </ul>
  </div>

<div class="separador"></div>
<div class="footer-bottomline">
  <div></div>
  <div class="footer__texto">&copy; <?= date('Y') ?> Merkaza</div>

</div>


</footer>

<script src="<?= $base ?>footer/footer.js"></script>
