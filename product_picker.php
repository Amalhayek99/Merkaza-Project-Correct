<?php

if (!function_exists('render_product_picker')) {
  function render_product_picker($conn, $name = 'product_id', $selected_id = null, $label = 'Product', $text_field_name = null) {
    // Fetch minimal data once
    $rows = $conn->query("SELECT id, Name FROM products ORDER BY Name ASC");
    $products = [];
    $selected_name = '';
    while ($r = $rows->fetch_assoc()) {
      $id = (int)$r['id'];
      $nm = (string)$r['Name'];
      // NOTE: if duplicate names exist, later ones override earlier in this simple map.
      $products[$nm] = $id;                 // map name -> id
      if ($selected_id !== null && $id === (int)$selected_id) $selected_name = $nm;
    }

    // Unique ids so you can reuse multiple pickers on one page if needed
    $uid = htmlspecialchars($name . '_' . substr(md5($name . mt_rand()), 0, 6));
    ?>

    <?php if ($label !== ''): ?>
      <label for="<?= $uid ?>_input" class="form-label"><?= htmlspecialchars($label) ?></label>
    <?php endif; ?>

    <input
      type="text"
      id="<?= $uid ?>_input"
      <?= $text_field_name ? 'name="'.htmlspecialchars($text_field_name).'"' : '' ?>
      placeholder="Type to search…"
      list="<?= $uid ?>_list"
      autocomplete="off"
      value="<?= htmlspecialchars($selected_name) ?>"
      onfocus="this.dispatchEvent(new Event('input'))" />

    <datalist id="<?= $uid ?>_list">
      <?php foreach ($products as $nm => $id): ?>
        <option value="<?= htmlspecialchars($nm) ?>"></option>
      <?php endforeach; ?>
    </datalist>

    <!-- Hidden field that actually submits the product id -->
    <input type="hidden" name="<?= htmlspecialchars($name) ?>" id="<?= $uid ?>_hidden"
           value="<?= $selected_id !== null ? (int)$selected_id : '' ?>">

    <script>
    (function(){
      // Tiny mapper: product name -> id
      const MAP = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
      const input  = document.getElementById('<?= $uid ?>_input');
      const hidden = document.getElementById('<?= $uid ?>_hidden');

      // When user picks or types an exact name, fill hidden id; otherwise clear it
      input.addEventListener('input', function(){
        const v = this.value.trim();
        let id = MAP[v] ?? null; // exact match (case-sensitive)

        if (id == null) {
          // case-insensitive exact match
          const lower = v.toLowerCase();
          for (const [k, val] of Object.entries(MAP)) {
            if (k.toLowerCase() === lower) { id = val; break; }
          }
        }
        hidden.value = id ? String(id) : '';
      });
    })();
    </script>
    <?php
  }
}
