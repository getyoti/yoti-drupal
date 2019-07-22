<?php

/**
 * @file
 * Default theme implementation to display a Yoti button.
 *
 * Available variables:
 * - $button_id: Button ID.
 * - $is_linked: TRUE if the account is linked.
 */
?>
<div class="yoti-connect">
  <?php if($is_linked): ?>
    <strong>Yoti</strong> Linked
  <?php else: ?>
    <div class="yoti-button" id="<?php print $button_id; ?>"></div>
  <?php endif; ?>
</div>
