<?php
/** @var array $events|$items */
/** @var string $mode full|items */

$items = $mode === 'full' ? $events : ($items ?? []);
$currentMonth = '';
?>

<?php if ($mode === 'full'): ?>
<div class="evm-events-list">
    <?php endif; ?>

    <?php foreach ($items as $event): ?>
        <?php
        $placesMap = [
            'place1' => 'Großer Saal',
            'place2' => 'Kleiner Saal',
            'place3' => 'Foyer',
        ];

        $selected = [];
        foreach ($placesMap as $key => $label) {
            if (!empty($event[$key]) && (int)$event[$key] > 0) {
                $selected[] = $label;
            }
        }
        $halls = !empty($selected) ? esc_html(implode(', ', $selected)) : '';
        $from = new DateTimeImmutable($event['fromdate']);
        $timestamp = $from->getTimestamp();

        $monthLabel = date_i18n('F Y', $timestamp);
        if ($monthLabel !== $currentMonth):
            $currentMonth = $monthLabel;
            ?>
            <div class="evm-month"><?= esc_html($monthLabel) ?></div>
        <?php endif; ?>
        <?php $eventDate = date_i18n('l, d. F Y', $timestamp);?>
        <div class="evm-event">
            <div class="evm-event-date"><?= $eventDate?></div>
            <div class="evm-event-location"><?= $halls ?></div>
            <div class="evm-event-title"><?= esc_html($event['title'] ?? '') ?></div>
            <div class="evm-event-icons">
                <?php if ($event['publish'] >= 2): ?>
                    <a href="#"
                        class="evm-open-popup"
                        data-popup="349"
                        data-event-id="<?= (int)$event['id'] ?>">
                        <?php if ($event['organization'] === 'Kultur im Löwen'): ?>
                            Kultur im Löwen <img src="<?= esc_url(WPEVMANAGER_URL . 'assets/img/culture_icon.png') ?>" alt="Kultur im Löwen">
                        <?php endif; ?>
                        <?php if ($event['publish'] == 2): ?>
                            <img src="<?= esc_url(WPEVMANAGER_URL . 'assets/img/info_icon.jpg') ?>" alt="Info">
                        <?php elseif ($event['publish'] == 3): ?>
                            <img src="<?= esc_url(WPEVMANAGER_URL . 'assets/img/culture_icon.png') ?>" alt="Löwe">
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

</div>
<?php if ($mode === 'full'): ?>
    <div class="evm-events-more">
        <button class="evm-load-more">Weitere Veranstaltungen</button>
    </div>
<?php endif; ?>


