<?php
/** @var array $item */
?>
<div class="evm-event-detail">
    <div class="evm-detail-header">
        <h4 class="custom-h4"><?php echo wp_kses($item['title'] ?? '',[ 'br' => [] ]);?></h4>
        <?php if (!empty($item['addinfos'])): ?>
            <img src="<?php echo esc_url(WPEVMANAGER_URL . 'assets/img/kultur-im-loewen.png'); ?>"
                 alt="Kultur im Löwen" class="evm-detail-culture-image">
        <?php endif; ?>

    </div>
    <?php if (!empty($item['picture'])): ?>
        <div class="evm-detail-picture">
            <img src="<?php echo wp_kses_post($item['picture']); ?>" alt="<?php echo esc_attr($item['title']); ?>">
        </div>
    <?php endif; ?>

    <p class="evm-detail-date">
        <?php echo date_i18n('l, d. F Y', strtotime($item['fromdate'])); ?>
        <?php if (!empty($item['fromtime'])): ?>
            – <?php echo esc_html($item['fromtime']); ?>
        <?php endif; ?>
    </p>
    <?php if (!empty($item['organizer'])): ?>
        <p class="evm-detail-organizer">
            Veranstalter: <?php echo esc_html($item['organizer']); ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($item['descr1'])): ?>
        <div class="evm-detail-description custom-text">
            <?php echo wp_kses_post($item['descr1']); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($item['descr2'])): ?>
        <div class="evm-detail-description custom-text">
            <?php echo wp_kses_post($item['descr2']); ?>
        </div>
    <?php endif; ?>


</div>
