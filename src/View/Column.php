<?php

declare(strict_types=1);

namespace Crumbls\Layup\View;

use Crumbls\Layup\Forms\Components\SpanPicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\View\View;

class Column extends BaseView
{
    protected array $span = [
        'sm' => 12,
        'md' => 12,
        'lg' => 12,
        'xl' => 12,
    ];

    public function __construct(array $data = [], array $children = [])
    {
        parent::__construct($data, $children);

        if (isset($data['span'])) {
            $this->span = array_merge($this->span, $data['span']);
        }
    }

    /**
     * Set the column span for one or all breakpoints.
     *
     * @param  int|array<string, int>  $span
     */
    public function span(int|array $span): static
    {
        if (is_int($span)) {
            $this->span = [
                'sm' => $span,
                'md' => $span,
                'lg' => $span,
                'xl' => $span,
            ];
        } else {
            $this->span = array_merge($this->span, $span);
        }

        return $this;
    }

    /**
     * Get the column span configuration.
     *
     * @return array<string, int>
     */
    public function getSpan(): array
    {
        return $this->span;
    }

    /**
     * Two tabs: Design and Advanced.
     */
    public static function getFormSchema(): array
    {
        return [
            Tabs::make('settings')
                ->tabs([
                    Tab::make(__('layup::widgets.shared.tab_design'))
                        ->icon('heroicon-o-paint-brush')
                        ->schema(static::withLiveValidation(static::getDesignFormSchema())),
                    Tab::make(__('layup::widgets.shared.tab_advanced'))
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema(static::withLiveValidation(static::getAdvancedFormSchema())),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * Design tab: span pickers, alignment, overflow, padding, margin, background.
     */
    public static function getDesignFormSchema(): array
    {
        $breakpoints = config('layup.breakpoints', []);

        $spanPickers = [];
        foreach ($breakpoints as $key => $bp) {
            $spanPickers[] = SpanPicker::make("span.{$key}")
                ->label('')
                ->breakpointLabel($bp['label'] ?? $key)
                ->color(match ($key) {
                    'sm' => '#ef4444',
                    'md' => '#f59e0b',
                    'lg' => '#22c55e',
                    'xl' => '#3b82f6',
                    default => '#8b5cf6',
                })
                ->default(12);
        }

        return [
            ...$spanPickers,
            Select::make('align_self')
                ->label(__('layup::widgets.column.align_self'))
                ->options([
                    'auto' => __('layup::widgets.column.auto'),
                    'start' => __('layup::widgets.column.start'),
                    'center' => __('layup::widgets.column.center'),
                    'end' => __('layup::widgets.column.end'),
                    'stretch' => __('layup::widgets.column.stretch'),
                    'baseline' => __('layup::widgets.column.baseline'),
                ])
                ->default('auto'),
            Select::make('overflow')
                ->label(__('layup::widgets.column.overflow'))
                ->options([
                    'visible' => __('layup::widgets.column.visible'),
                    'hidden' => __('layup::widgets.column.hidden'),
                    'auto' => __('layup::widgets.column.auto'),
                    'scroll' => __('layup::widgets.column.scroll'),
                ])
                ->default('visible'),
            ...parent::getDesignFormSchema(),
        ];
    }

    public function render(): View
    {
        return view('layup::components.column', [
            'children' => $this->children,
            'span' => $this->span,
            'data' => $this->data,
            'isFirst' => $this->isFirst,
            'isLast' => $this->isLast,
        ]);
    }
}
