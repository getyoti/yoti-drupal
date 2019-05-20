<?php

/**
 * @file
 * Default theme implementation to display a Yoti button.
 *
 * Available variables:
 * - $app_id: Yoti App ID.
 * - $scenario_id: Yoti Scenario ID.
 * - $button_text: Button Text.
 * - $is_linked: TRUE if the account is linked.
 */
?>
<div class="yoti-connect">
  <?php if($is_linked): ?>
    <strong>Yoti</strong> Linked
  <?php else: ?>
    <span
      data-yoti-application-id="<?php print $app_id; ?>"
      data-yoti-type="inline"
      data-yoti-scenario-id="<?php print $scenario_id; ?>"
      data-size="small">
        <?php print $button_text; ?>
    </span>
  <?php endif; ?>
</div>
