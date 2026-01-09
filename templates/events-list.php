
<?php if (!empty($groups)): ?>
    <div class="evm-events-block">

        <?php foreach ($groups as $ym => $events): ?>
            <?php
            [$year, $month] = explode('-', $ym);
            $ts = mktime(0, 0, 0, (int)$month, 1, (int)$year);
            $monthLabel = strtoupper(wp_date('F', $ts));
            ?>

            <div class="evm-events-list">

                <?php foreach ($events as $event): ?>
                    <?php
                    // --- Säle ermitteln ---
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

                    $halls = !empty($selected)
                            ? esc_html(implode(', ', $selected))
                            : '';

                    // --- Datum ---
                    if (empty($event['fromdate']) || $event['fromdate'] === '0000-00-00') {
                        continue;
                    }

                    $from       = new DateTimeImmutable($event['fromdate']);
                    $timestamp  = $from->getTimestamp();

                    // Monatsüberschrift
                    $monthLabel = date_i18n('F Y', $timestamp);
                    if ($monthLabel !== $currentMonth) {
                        $currentMonth = $monthLabel;
                        ?>
                        <div class="evm-month"><?php echo esc_html($monthLabel); ?></div>
                        <?php
                    }

                    $eventDate = date_i18n('l, d. F Y', $timestamp);
                    ?>

                    <div class="evm-event">
                        <div class="evm-event-date">
                            <?php echo esc_html($eventDate); ?>
                        </div>

                        <div class="evm-event-location">
                            <?php echo $halls; ?>
                        </div>

                        <div class="evm-event-title">
                            <?php echo wp_kses($event['title'] ?? '',[ 'br' => [] ]); ?>
                        </div>

                        <div class="evm-event-icons">
                            <?php if ((int)$event['publish'] >= 2): ?>
                                <a href="#"
                                   class="evm-open-popup"
                                   data-popup="349"
                                   data-event-id="<?php echo (int)$event['id']; ?>">

                                    <?php if (($event['addinfos'] ?? '') === 'Kultur im Löwen'): ?>
                                        Kultur im Löwen
                                        <img src="<?php echo esc_url(WPEVMANAGER_URL . 'assets/img/culture_icon.png'); ?>"
                                             alt="Kultur im Löwen">
                                    <?php endif; ?>

                                    <?php if ((int)$event['publish'] === 2): ?>
                                        <img src="<?php echo esc_url(WPEVMANAGER_URL . 'assets/img/info_icon.jpg'); ?>"
                                             alt="Info">
                                    <?php elseif ((int)$event['publish'] === 3): ?>
                                        <img src="<?php echo esc_url(WPEVMANAGER_URL . 'assets/img/culture_icon.png'); ?>"
                                             alt="Löwe">
                                    <?php endif; ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php endforeach; ?>

            </div>
        <?php endforeach; ?>

    </div>
<?php endif; ?>

