<?php
/** চেকবক্স — সুইচের চেহারায়। */
$name    = $name ?? '';
$label   = $label ?? '';
$hint    = $hint ?? '';
$checked = (bool) ($checked ?? false);
$id      = $id ?? ('t_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name));
?>
<label class="toggle" for="<?= e($id) ?>">
    <input type="checkbox" id="<?= e($id) ?>" name="<?= e($name) ?>" value="1" <?= $checked ? 'checked' : '' ?>>
    <span class="toggle-track"><span class="toggle-thumb"></span></span>
    <span class="toggle-text">
        <span class="toggle-label"><?= e($label) ?></span>
        <?php if ($hint !== ''): ?><span class="toggle-hint"><?= e($hint) ?></span><?php endif; ?>
    </span>
</label>
