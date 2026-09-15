<?php
declare(strict_types=1);

function rel_period(?array $source = null): array
{
    $source = $source ?? $_GET;
    $inicio = (string) ($source['inicio'] ?? date('Y-m-01'));
    $fim = (string) ($source['fim'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio)) {
        $inicio = date('Y-m-01');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim)) {
        $fim = date('Y-m-d');
    }
    if ($inicio > $fim) {
        [$inicio, $fim] = [$fim, $inicio];
    }

    return [
        'inicio' => $inicio,
        'fim' => $fim,
        'dateWhere' => 'BETWEEN :inicio AND :fim',
        'params' => ['inicio' => $inicio . ' 00:00:00', 'fim' => $fim . ' 23:59:59'],
    ];
}

function rel_previous_period(string $inicio, string $fim): array
{
    $len = (int) ((strtotime($fim) - strtotime($inicio)) / 86400) + 1;
    $prevFim = date('Y-m-d', strtotime($inicio . ' -1 day'));

    return [
        'inicio' => date('Y-m-d', strtotime($prevFim . ' -' . ($len - 1) . ' days')),
        'fim' => $prevFim,
    ];
}

function rel_pct(float $atual, float $anterior): float
{
    if ((float) $anterior == 0.0) {
        return $atual > 0 ? 100.0 : 0.0;
    }

    return (($atual - $anterior) / abs($anterior)) * 100.0;
}

function rel_trend(float $pct, string $invert = ''): string
{
    $delta = round($pct, 1);
    if ($invert === 'down-good') {
        $good = $delta <= 0;
    } else {
        $good = $delta >= 0;
    }
    $icon = $delta >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right';
    $cls = $good ? 'text-success' : 'text-danger';

    return '<span class="' . $cls . ' small fw-bold"><i class="bi ' . $icon . '"></i> ' . number_format(abs($delta), 1, ',', '.') . '%</span>';
}

function rel_axis(float $value): string
{
    $value = (float) $value;
    if ($value >= 1000000) {
        return rtrim(rtrim(number_format($value / 1000000, 2, ',', '.'), '0'), ',') . ' M';
    }
    if ($value >= 1000) {
        return rtrim(rtrim(number_format($value / 1000, 2, ',', '.'), '0'), ',') . ' mil';
    }

    return number_format($value, 0, ',', '.');
}

function rel_axis_money(float $value): string
{
    return 'R$ ' . rel_axis($value);
}

function rel_fill_daily(array $rows, string $inicio, string $fim): array
{
    $map = [];
    foreach ($rows as $row) {
        $map[(string) $row['bucket']] = $row;
    }
    $out = [];
    $current = new DateTime($inicio);
    $end = new DateTime($fim);
    $end->modify('+1 day');
    for (; $current < $end; $current->modify('+1 day')) {
        $key = $current->format('Y-m-d');
        $out[] = [
            'label' => $current->format('d/m'),
            'n' => isset($map[$key]) ? (int) $map[$key]['n'] : 0,
            'valor' => isset($map[$key]) ? (float) $map[$key]['valor'] : 0.0,
        ];
    }

    return $out;
}

function rel_bucket_for_period(string $inicio, string $fim): array
{
    $days = (int) ((strtotime($fim) - strtotime($inicio)) / 86400) + 1;
    if ($days <= 62) {
        return ['d', 'DATE(COALESCE(%s.finalizada_em, %s.criado_em))'];
    }
    if ($days <= 366) {
        return ['w', 'DATE_SUB(DATE(COALESCE(%s.finalizada_em, %s.criado_em)), INTERVAL WEEKDAY(COALESCE(%s.finalizada_em, %s.criado_em)) DAY)'];
    }

    return ['m', 'DATE_FORMAT(COALESCE(%s.finalizada_em, %s.criado_em), \'%%Y-%%m-01\')'];
}

function rel_bucket_label(string $bucket, string $value): string
{
    if ($bucket === 'w') {
        return date('d/m', strtotime($value));
    }
    if ($bucket === 'm') {
        return date('m/Y', strtotime($value . '-01'));
    }

    return date('d/m', strtotime($value));
}

function rel_svg_bars(array $data, array $opt = []): string
{
    $width = (int) ($opt['width'] ?? 760);
    $height = (int) ($opt['height'] ?? 250);
    $padL = (int) ($opt['padL'] ?? 54);
    $padR = (int) ($opt['padR'] ?? 8);
    $padT = (int) ($opt['padT'] ?? 16);
    $padB = (int) ($opt['padB'] ?? 30);
    $money = (bool) ($opt['money'] ?? true);
    $showValues = (bool) ($opt['showValues'] ?? false);
    $colors = (array) ($opt['colors'] ?? ['#e8407a', '#f4621f']);
    $innerW = $width - $padL - $padR;
    $innerH = $height - $padT - $padB;

    $max = 1.0;
    foreach ($data as $item) {
        $max = max($max, (float) $item['value'], isset($item['value2']) ? (float) $item['value2'] : 0.0);
    }

    $axis = $money ? 'rel_axis_money' : 'rel_axis';
    $grid = '';
    $lines = 4;
    for ($i = 0; $i <= $lines; $i++) {
        $ratio = $i / $lines;
        $yy = $padT + $innerH - ($innerH * $ratio);
        $grid .= '<line x1="' . $padL . '" y1="' . $yy . '" x2="' . ($width - $padR) . '" y2="' . $yy . '" stroke="#f0e4da" stroke-width="1"/>';
        $grid .= '<text x="' . ($padL - 8) . '" y="' . ($yy + 4) . '" text-anchor="end" font-size="10" fill="#9a8578">' . $axis($max * $ratio) . '</text>';
    }

    $count = max(1, count($data));
    $group = $innerW / $count;
    $has2 = isset($data[0]['value2']);
    $barW = min(26.0, $group * 0.72);
    $gap = $has2 ? max(3.0, min(10.0, $barW * 0.18)) : 0.0;
    $singleW = $has2 ? ($barW - $gap) / 2 : $barW;

    $bars = '';
    $labels = '';
    $labelEvery = max(1, (int) ceil($count / 26));
    foreach ($data as $i => $item) {
        $cx = $padL + $group * $i + $group / 2;
        $v1 = (float) $item['value'];
        $v2 = isset($item['value2']) ? (float) $item['value2'] : 0.0;
        $h1 = ($v1 / $max) * $innerH;
        $h2 = ($v2 / $max) * $innerH;
        $tip = e((string) ($item['tooltip'] ?? $item['label']));

        if ($has2) {
            $bars .= '<rect x="' . round($cx - $barW / 2, 1) . '" y="' . round($padT + $innerH - $h1, 1) . '" width="' . round($singleW, 1) . '" height="' . round(max(1, $h1), 1) . '" fill="' . $colors[0] . '" rx="2"><title>' . $tip . '</title></rect>';
            $bars .= '<rect x="' . round($cx - $barW / 2 + $singleW + $gap, 1) . '" y="' . round($padT + $innerH - $h2, 1) . '" width="' . round($singleW, 1) . '" height="' . round(max(1, $h2), 1) . '" fill="' . $colors[1] . '" rx="2"><title>' . $tip . '</title></rect>';
        } else {
            $bars .= '<rect x="' . round($cx - $barW / 2, 1) . '" y="' . round($padT + $innerH - $h1, 1) . '" width="' . round($barW, 1) . '" height="' . round(max(1, $h1), 1) . '" fill="' . $colors[0] . '" rx="2"><title>' . $tip . '</title></rect>';
            if ($showValues && $v1 > 0) {
                $bars .= '<text x="' . $cx . '" y="' . round($padT + $innerH - $h1 - 5, 1) . '" text-anchor="middle" font-size="9" fill="#6b1f3a">' . ($money ? rel_axis_money($v1) : number_format($v1, 0, ',', '.')) . '</text>';
            }
        }

        if ($i % $labelEvery === 0) {
            $labels .= '<text x="' . $cx . '" y="' . ($padT + $innerH + 18) . '" text-anchor="middle" font-size="9" fill="#8a6a5c">' . e((string) $item['label']) . '</text>';
        }
    }

    return '<svg viewBox="0 0 ' . $width . ' ' . $height . '" role="img" style="width:100%;height:auto;display:block">'
        . $grid . $bars . $labels
        . '</svg>';
}

function rel_svg_curve(array $data, array $opt = []): string
{
    $width = (int) ($opt['width'] ?? 760);
    $height = (int) ($opt['height'] ?? 250);
    $padL = (int) ($opt['padL'] ?? 54);
    $padR = (int) ($opt['padR'] ?? 8);
    $padT = (int) ($opt['padT'] ?? 16);
    $padB = (int) ($opt['padB'] ?? 30);
    $money = (bool) ($opt['money'] ?? true);
    $colors = (array) ($opt['colors'] ?? ['#e8407a', '#f4621f']);
    $innerW = $width - $padL - $padR;
    $innerH = $height - $padT - $padB;

    $max = 1.0;
    foreach ($data as $item) {
        $max = max($max, (float) $item['value'], isset($item['value2']) ? (float) $item['value2'] : 0.0);
    }

    $count = max(1, count($data));
    $px = static fn (int $i): float => $padL + ($count === 1 ? $innerW / 2 : ($innerW * ($i / ($count - 1))));
    $py = static fn (float $v): float => $padT + $innerH - (($v / $max) * $innerH);

    $axis = $money ? 'rel_axis_money' : 'rel_axis';
    $grid = '';
    $lines = 4;
    for ($i = 0; $i <= $lines; $i++) {
        $ratio = $i / $lines;
        $yy = $padT + $innerH - ($innerH * $ratio);
        $grid .= '<line x1="' . $padL . '" y1="' . $yy . '" x2="' . ($width - $padR) . '" y2="' . $yy . '" stroke="#f0e4da" stroke-width="1"/>';
        $grid .= '<text x="' . ($padL - 8) . '" y="' . ($yy + 4) . '" text-anchor="end" font-size="10" fill="#9a8578">' . $axis($max * $ratio) . '</text>';
    }

    $points1 = [];
    $points2 = [];
    foreach ($data as $i => $item) {
        $points1[] = round($px($i), 1) . ',' . round($py((float) $item['value']), 1);
        if (isset($item['value2'])) {
            $points2[] = round($px($i), 1) . ',' . round($py((float) $item['value2']), 1);
        }
    }
    $line1 = implode(' ', $points1);
    $line2 = implode(' ', $points2);
    $area1 = $padL . ',' . ($padT + $innerH) . ' ' . $line1 . ' ' . ($width - $padR) . ',' . ($padT + $innerH);

    $dots = '';
    $labels = '';
    $labelEvery = max(1, (int) ceil($count / 20));
    foreach ($data as $i => $item) {
        $tip = e((string) ($item['tooltip'] ?? $item['label']));
        $dots .= '<circle cx="' . round($px($i), 1) . '" cy="' . round($py((float) $item['value']), 1) . '" r="2.6" fill="' . $colors[0] . '"><title>' . $tip . '</title></circle>';
        if (isset($item['value2'])) {
            $dots .= '<circle cx="' . round($px($i), 1) . '" cy="' . round($py((float) $item['value2']), 1) . '" r="2.6" fill="' . $colors[1] . '"><title>' . $tip . '</title></circle>';
        }
        if ($i % $labelEvery === 0) {
            $labels .= '<text x="' . $px($i) . '" y="' . ($padT + $innerH + 18) . '" text-anchor="middle" font-size="9" fill="#8a6a5c">' . e((string) $item['label']) . '</text>';
        }
    }

    return '<svg viewBox="0 0 ' . $width . ' ' . $height . '" role="img" style="width:100%;height:auto;display:block">'
        . $grid
        . '<polygon points="' . $area1 . '" fill="' . $colors[0] . '" fill-opacity="0.09"/>'
        . '<polyline points="' . $line1 . '" fill="none" stroke="' . $colors[0] . '" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>'
        . ($points2 !== [] ? '<polyline points="' . $line2 . '" fill="none" stroke="' . $colors[1] . '" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round" stroke-dasharray="5 4"/>' : '')
        . $dots . $labels
        . '</svg>';
}

function rel_chart_legend(array $items): string
{
    $html = '<div class="d-flex flex-wrap gap-3 mb-2 small">';
    foreach ($items as $i => $item) {
        $color = $item['color'] ?? ($i === 0 ? '#e8407a' : '#f4621f');
        $html .= '<span class="d-inline-flex align-items-center gap-1 text-secondary"><span class="d-inline-block rounded-circle" style="width:10px;height:10px;background:' . $color . '"></span>' . e((string) $item['label']) . '</span>';
    }
    return $html . '</div>';
}