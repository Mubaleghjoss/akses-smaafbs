<?php

namespace App\Filament\Widgets;

use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Str;

abstract class InteractiveDoughnutChartWidget extends ChartWidget
{
    protected string $view = 'filament.widgets.interactive-doughnut-chart';

    protected ?string $pollingInterval = null;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getViewData(): array
    {
        return [
            'chartIdentifier' => $this->getChartIdentifier(),
        ];
    }

    protected function getChartIdentifier(): string
    {
        return Str::kebab(class_basename(static::class));
    }

    protected function getOptions(): array|RawJs|null
    {
        $chartIdentifier = $this->getChartIdentifier();

        $options = <<<'JS'
        {
            maintainAspectRatio: false,
            cutout: '68%',
            scales: {
                x: {
                    display: false,
                    grid: {
                        display: false,
                    },
                    border: {
                        display: false,
                    },
                },
                y: {
                    display: false,
                    grid: {
                        display: false,
                    },
                    border: {
                        display: false,
                    },
                },
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 16,
                    },
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const detail = context.dataset.segmentDetails?.[context.dataIndex];

                            if (! detail) {
                                return `${context.label}: ${context.formattedValue}`;
                            }

                            return `${detail.label}: ${detail.countLabel}`;
                        },
                        afterLabel: function (context) {
                            const detail = context.dataset.segmentDetails?.[context.dataIndex];

                            return detail?.shortDescription ?? '';
                        },
                    },
                },
            },
            onClick: function (event, elements, chart) {
                if (! elements.length) {
                    return;
                }

                const point = elements[0];
                const detail = chart.data.datasets?.[point.datasetIndex]?.segmentDetails?.[point.index];

                if (! detail) {
                    return;
                }

                window.dispatchEvent(new CustomEvent('interactive-doughnut-chart:segment-selected', {
                    detail: {
                        chartId: '__CHART_IDENTIFIER__',
                        payload: detail,
                    },
                }));
            },
        }
        JS;

        return RawJs::make(str_replace('__CHART_IDENTIFIER__', $chartIdentifier, $options));
    }
}
