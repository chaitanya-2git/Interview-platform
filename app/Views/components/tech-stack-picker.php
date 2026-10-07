<?php
$selected=array_fill_keys(array_map('intval',$selectedTechnologyIds??[]),true);
$pickerId=$pickerId??'tech-stack';$pickerLabel=$pickerLabel??'Tech Stack';$pickerHint=$pickerHint??'Search and select up to 20 technologies.';
?>
<fieldset class="field tech-stack-field" data-tech-picker data-max="20">
 <legend><?= $e($pickerLabel) ?></legend>
 <div class="tech-picker-control" data-tech-control><div class="tech-chip-list" data-tech-chips aria-live="polite"></div><input id="<?= $e($pickerId) ?>" type="search" data-tech-search autocomplete="off" placeholder="Search skills..." aria-controls="<?= $e($pickerId) ?>-options" aria-expanded="false"></div>
 <div class="tech-option-list" id="<?= $e($pickerId) ?>-options" data-tech-options>
 <?php foreach($technologies as $technology):$checked=isset($selected[(int)$technology['id']]);$searchValue=mb_strtolower(implode(' ',array_filter([$technology['name'],$technology['category_name']??'',str_replace('|',' ',(string)($technology['aliases']??''))])));?>
  <label class="tech-option" data-tech-option data-search-value="<?= $e($searchValue) ?>"><input type="checkbox" name="technology_ids[]" value="<?= $e($technology['id']) ?>" <?= $checked?'checked':'' ?> <?= !($technology['active']??true)&&!$checked?'disabled':'' ?>><span><?= $e($technology['name']) ?></span><small><?= $e($technology['category_name']??'Uncategorized') ?><?= !($technology['active']??true)?' · Historical · inactive':'' ?></small></label>
 <?php endforeach;?>
 <p class="tech-no-results" data-tech-empty>Type at least 2 characters to search the technology catalog.</p>
 </div>
 <span class="field-hint"><?= $e($pickerHint) ?></span><?= \App\Support\Ui::error($errors??[],'technology_ids') ?>
</fieldset>
