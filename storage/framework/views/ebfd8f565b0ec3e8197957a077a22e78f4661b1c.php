<?php
    $search_id = 'apply-filters';
    if (isset($customId)) {
         $search_id = $customId;
    }
?>
<div class="input-icon mb-1" style="width: 215px;">
    <button class="btn btn-primary btn-primary--icon" id="<?php echo e($search_id); ?>">
        <i class="la la-search"></i>
        Search
    </button>

    <?php if(isset($custom_reset) && $custom_reset != ''): ?>

        <button class="btn btn-secondary btn-secondary--icon ml-3" onclick="resetCustomFilters();" id="reset-filters">
            <i class="la la-close"></i>
            Reset
        </button>
    <?php else: ?>
        <button class="btn btn-secondary btn-secondary--icon ml-3" onclick="resetFilters();" id="reset-filters">
            <i class="la la-close"></i>
            Reset
        </button>
    <?php endif; ?>


</div>
<?php /**PATH /var/www/cuterav2.test/resources/views/admin/partials/filter-buttons.blade.php ENDPATH**/ ?>