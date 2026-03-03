<?php

declare(strict_types=1);

namespace Crumbls\Layup\Resources\PageResource\Pages;

use Crumbls\Layup\Resources\PageResource;
use Crumbls\Layup\Support\ContentValidator;
use Crumbls\Layup\Support\PageTemplate;
use Crumbls\Layup\Support\WidgetRegistry;
use Crumbls\Layup\View\Column;
use Crumbls\Layup\View\Row;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected string $view = 'layup::livewire.page-builder';

    protected Width|string|null $maxContentWidth = 'full';

    /** @var array Excluded from Filament's form hydration via */
    public array $pageContent = [];

    public ?string $editingRowId = null;

    public ?string $editingColumnId = null;

    public ?string $editingWidgetId = null;

    public ?string $editingWidgetType = null;

    public array $rowSettings = [];

    public array $columnSettings = [];

    public array $widgetData = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->pageContent = $this->record->content ?? ['rows' => []];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('revisions')
                ->label(__('layup::resource.revision_history'))
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->slideOver()
                ->modalWidth('2xl')
                ->modalHeading(__('layup::resource.revision_history'))
                ->modalDescription(__('layup::resource.revision_history_description'))
                ->modalContent(fn (): \Illuminate\Contracts\View\View => $this->getRevisionHistoryView())
                ->modalFooterActions([])
                ->action(fn (): null => null),
            Action::make('saveAsTemplate')
                ->label(__('layup::resource.save_as_template'))
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->schema([
                   TextInput::make('template_name')
                        ->label(__('layup::resource.template_name'))
                        ->required()
                        ->default(fn (): string => $this->record->title . ' Template'),
                    TextInput::make('template_description')
                        ->label(__('layup::resource.description'))
                        ->nullable(),
                ])
                ->action(function (array $data): void {
                    PageTemplate::saveFromPage(
                        $data['template_name'],
                        $this->record->content ?? ['rows' => []],
                        $data['template_description'] ?? null,
                    );
                    Notification::make()->success()->title(__('layup::notifications.template_saved'))->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRevisionHistoryView(): \Illuminate\Contracts\View\View
    {
        $revisions = $this->record->revisions()
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return view('layup::revision-history', [
            'revisions' => $revisions,
            'currentPageId' => $this->record->id,
        ]);
    }

    public function restoreRevision(int $revisionId): void
    {
        $revision = $this->record->revisions()->findOrFail($revisionId);

        $this->record->update(['content' => $revision->content]);
        $this->pageContent = $revision->content;
        $this->syncContent();

        Notification::make()
            ->title(__('layup::notifications.revision_restored'))
            ->body(__('layup::notifications.revision_restored_body', ['time' => $revision->created_at->diffForHumans()]))
            ->success()
            ->send();

        $this->dispatch('close-modal', id: 'revisions');
    }

    // ─── Row Operations ──────────────────────────────────────

    protected function refreshContent(): void
    {
        $this->record->refresh();
        $this->pageContent = $this->record->content ?? ['rows' => []];
    }

    protected function syncContent(): void
    {
        $this->dispatch('content-updated', $this->pageContent);
    }

    /**
     * Restore content from Alpine's undo/redo history.
     */
    public function restoreContent(array $content): void
    {
        $result = (new ContentValidator)->validate($content);

        if (! $result->passes()) {
            Notification::make()
                ->title(__('layup::notifications.invalid_content'))
                ->body(implode(' ', $result->errors()))
                ->danger()
                ->send();

            return;
        }

        $this->pageContent = $content;
        $this->record->update(['content' => $this->pageContent]);
    }

    public function addRow(array $spans): void
    {
        $row = [
            'id' => 'row_' . Str::random(8),
            'settings' => [
                'gap' => 'gap-4',
                'alignment' => 'justify-start',
                'verticalAlignment' => 'items-stretch',
            ],
            'columns' => collect($spans)->map(fn (int $span): array => [
                'id' => 'col_' . Str::random(8),
                'span' => ['sm' => 12, 'md' => $span, 'lg' => $span, 'xl' => $span],
                'settings' => ['padding' => 'p-4', 'background' => 'transparent'],
                'widgets' => [],
            ])->values()->all(),
        ];

        $this->pageContent['rows'][] = $row;
        $this->record->update(['content' => $this->pageContent]);
        $this->syncContent();
    }

    public function addRowAt(array $spans, int $position): void
    {
        $row = [
            'id' => 'row_' . Str::random(8),
            'settings' => [
                'gap' => 'gap-4',
                'alignment' => 'justify-start',
                'verticalAlignment' => 'items-stretch',
            ],
            'columns' => collect($spans)->map(fn (int $span): array => [
                'id' => 'col_' . Str::random(8),
                'span' => ['sm' => 12, 'md' => $span, 'lg' => $span, 'xl' => $span],
                'settings' => ['padding' => 'p-4', 'background' => 'transparent'],
                'widgets' => [],
            ])->values()->all(),
        ];

        array_splice($this->pageContent['rows'], $position, 0, [$row]);
        $this->record->update(['content' => $this->pageContent]);
        $this->syncContent();
    }

    public function confirmDeleteRow(string $rowId): void
    {
        $this->editingRowId = $rowId;
        $this->mountAction('deleteRowAction');
    }

    public function deleteRowAction(): Action
    {
        return Action::make('deleteRowAction')
            ->label(__('layup::resource.delete_row'))
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('layup::resource.delete_row'))
            ->modalDescription(__('layup::resource.delete_row_description'))
            ->action(function (): void {
                $this->refreshContent();
                $this->pageContent['rows'] = collect($this->pageContent['rows'])
                    ->reject(fn ($row): bool => $row['id'] === $this->editingRowId)
                    ->values()
                    ->all();

                $this->record->update(['content' => $this->pageContent]);
                $this->syncContent();
                $this->editingRowId = null;

                Notification::make()->title(__('layup::notifications.row_deleted'))->success()->duration(2000)->send();
            });
    }

    public function moveRow(string $rowId, string $direction): void
    {
        $rows = collect($this->pageContent['rows']);
        $index = $rows->search(fn ($r): bool => $r['id'] === $rowId);

        if ($index === false) {
            return;
        }

        $newIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if ($newIndex < 0 || $newIndex >= $rows->count()) {
            return;
        }

        $arr = $rows->all();
        [$arr[$index], $arr[$newIndex]] = [$arr[$newIndex], $arr[$index]];
        $this->pageContent['rows'] = array_values($arr);

        $this->record->update(['content' => $this->pageContent]);
        $this->syncContent();
    }

    /**
     * Add a column to an existing row.
     */
    public function addColumn(string $rowId, int $span = 6): void
    {
        $this->refreshContent();

        foreach ($this->pageContent['rows'] as &$row) {
            if ($row['id'] === $rowId) {
                $row['columns'][] = [
                    'id' => 'col_' . Str::random(8),
                    'span' => ['sm' => 12, 'md' => $span, 'lg' => $span, 'xl' => $span],
                    'settings' => ['padding' => 'p-4', 'background' => 'transparent'],
                    'widgets' => [],
                ];
                break;
            }
        }

        $this->record->update(['content' => $this->pageContent]);
        $this->syncContent();
    }

    /**
     * Delete a column from a row (with confirmation modal).
     */
    public function confirmDeleteColumn(string $rowId, string $columnId): void
    {
        $this->editingRowId = $rowId;
        $this->editingColumnId = $columnId;
        $this->mountAction('deleteColumnAction');
    }

    public function deleteColumnAction(): Action
    {
        return Action::make('deleteColumnAction')
            ->label(__('layup::resource.delete_column'))
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('layup::resource.delete_column'))
            ->modalDescription(__('layup::resource.delete_column_description'))
            ->action(function (): void {
                $this->refreshContent();

                foreach ($this->pageContent['rows'] as &$row) {
                    if ($row['id'] === $this->editingRowId) {
                        $row['columns'] = collect($row['columns'])
                            ->reject(fn ($col): bool => $col['id'] === $this->editingColumnId)
                            ->values()
                            ->all();
                        break;
                    }
                }

                $this->record->update(['content' => $this->pageContent]);
                $this->syncContent();
                $this->editingRowId = null;
                $this->editingColumnId = null;

                Notification::make()->title(__('layup::notifications.column_deleted'))->success()->duration(2000)->send();
            });
    }

    /**
     * Move a column left or right within a row.
     */
    public function moveColumn(string $rowId, string $columnId, string $direction): void
    {
        $this->refreshContent();

        foreach ($this->pageContent['rows'] as &$row) {
            if ($row['id'] !== $rowId) {
                continue;
            }

            $cols = collect($row['columns']);
            $index = $cols->search(fn ($c): bool => $c['id'] === $columnId);
            if ($index === false) {
                break;
            }

            $newIndex = $direction === 'left' ? $index - 1 : $index + 1;
            if ($newIndex < 0 || $newIndex >= $cols->count()) {
                break;
            }

            $arr = $cols->all();
            [$arr[$index], $arr[$newIndex]] = [$arr[$newIndex], $arr[$index]];
            $row['columns'] = array_values($arr);
            break;
        }

        $this->record->update(['content' => $this->pageContent]);
        $this->syncContent();
    }

    /**
     * Move a widget up or down within a column.
     */
    public function moveWidget(string $rowId, string $columnId, string $widgetId, string $direction): void
    {
        $this->refreshContent();

        foreach ($this->pageContent['rows'] as &$row) {
            if ($row['id'] !== $rowId) {
                continue;
            }
            foreach ($row['columns'] as &$col) {
                if ($col['id'] !== $columnId) {
                    continue;
                }

                $widgets = collect($col['widgets']);
                $index = $widgets->search(fn ($w): bool => $w['id'] === $widgetId);
                if ($index === false) {
                    break 2;
                }

                $newIndex = $direction === 'up' ? $index - 1 : $index + 1;
                if ($newIndex < 0 || $newIndex >= $widgets->count()) {
                    break 2;
                }

                $arr = $widgets->all();
                [$arr[$index], $arr[$newIndex]] = [$arr[$newIndex], $arr[$index]];
                $col['widgets'] = array_values($arr);
                break 2;
            }
        }

        $this->record->update(['content' => $this->pageContent]);
        $this->syncContent();
    }

    public function moveWidgetTo(string $sourceRowId, string $sourceColId, string $widgetId, string $targetRowId, string $targetColId, int $position): void
    {
        $this->refreshContent();

        $widget = null;

        // Remove from source
        foreach ($this->pageContent['rows'] as &$row) {
            if ($row['id'] !== $sourceRowId) {
                continue;
            }
            foreach ($row['columns'] as &$col) {
                if ($col['id'] !== $sourceColId) {
                    continue;
                }
                $index = collect($col['widgets'])->search(fn ($w): bool => $w['id'] === $widgetId);
                if ($index === false) {
                    return;
                }
                $widget = $col['widgets'][$index];
                array_splice($col['widgets'], $index, 1);
                break 2;
            }
        }
        unset($row, $col);

        if (! $widget) {
            return;
        }

        // Insert into target
        foreach ($this->pageContent['rows'] as &$row) {
            if ($row['id'] !== $targetRowId) {
                continue;
            }
            foreach ($row['columns'] as &$col) {
                if ($col['id'] !== $targetColId) {
                    continue;
                }
                array_splice($col['widgets'], $position, 0, [$widget]);
                break 2;
            }
        }
        unset($row, $col);

        $this->record->update(['content' => $this->pageContent]);
        $this->syncContent();
    }

    public function moveRowTo(string $rowId, int $targetIndex): void
    {
        $rows = $this->pageContent['rows'];
        $sourceIndex = collect($rows)->search(fn ($r): bool => $r['id'] === $rowId);
        if ($sourceIndex === false) {
            return;
        }

        $row = $rows[$sourceIndex];
        array_splice($rows, $sourceIndex, 1);
        array_splice($rows, $targetIndex, 0, [$row]);

        $this->pageContent['rows'] = $rows;
        $this->record->update(['content' => $this->pageContent]);
        $this->syncContent();
    }

    // ─── Duplication ─────────────────────────────────────────

    public function duplicateRow(string $rowId): void
    {
        $this->refreshContent();

        $rows = collect($this->pageContent['rows']);
        $index = $rows->search(fn ($r): bool => $r['id'] === $rowId);
        if ($index === false) {
            return;
        }

        $original = $rows[$index];
        $clone = $this->deepCloneRow($original);

        array_splice($this->pageContent['rows'], $index + 1, 0, [$clone]);
        $this->record->update(['content' => $this->pageContent]);
        $this->syncContent();

        Notification::make()->title(__('layup::notifications.row_duplicated'))->success()->duration(2000)->send();
    }

    public function duplicateWidget(string $rowId, string $columnId, string $widgetId): void
    {
        $this->refreshContent();

        foreach ($this->pageContent['rows'] as &$row) {
            if ($row['id'] !== $rowId) {
                continue;
            }
            foreach ($row['columns'] as &$col) {
                if ($col['id'] !== $columnId) {
                    continue;
                }

                $widgets = collect($col['widgets']);
                $index = $widgets->search(fn ($w): bool => $w['id'] === $widgetId);
                if ($index === false) {
                    break 2;
                }

                $original = $col['widgets'][$index];
                $clone = [
                    'id' => 'widget_' . Str::random(8),
                    'type' => $original['type'],
                    'data' => $original['data'] ?? [],
                ];

                array_splice($col['widgets'], $index + 1, 0, [$clone]);
                break 2;
            }
        }

        $this->record->update(['content' => $this->pageContent]);
        $this->syncContent();

        Notification::make()->title(__('layup::notifications.widget_duplicated'))->success()->duration(2000)->send();
    }

    protected function deepCloneRow(array $row): array
    {
        $clone = $row;
        $clone['id'] = 'row_' . Str::random(8);
        $clone['columns'] = collect($row['columns'])->map(function (array $col): array {
            $col['id'] = 'col_' . Str::random(8);
            $col['widgets'] = collect($col['widgets'] ?? [])->map(function (array $widget): array {
                $widget['id'] = 'widget_' . Str::random(8);

                return $widget;
            })->all();

            return $col;
        })->all();

        return $clone;
    }

    // ─── Row Settings Slideover ──────────────────────────────

    public function editRow(string $rowId): void
    {
        $this->editingRowId = $rowId;
        $row = collect($this->pageContent['rows'])->firstWhere('id', $rowId);
        $this->rowSettings = $row['settings'] ?? [];

        $this->mountAction('editRowAction');
    }

    public function editRowAction(): Action
    {
        return Action::make('editRowAction')
            ->label(__('layup::resource.row_settings'))
            ->slideOver()
            ->fillForm(fn (): array => $this->rowSettings)
            ->schema(Row::getFormSchema())
            ->action(function (array $data): void {
                $this->refreshContent();
                $this->pageContent['rows'] = collect($this->pageContent['rows'])->map(function (array $row) use ($data): array {
                    if ($row['id'] === $this->editingRowId) {
                        $row['settings'] = $data;
                    }

                    return $row;
                })->all();

                $this->record->update(['content' => $this->pageContent]);
                $this->syncContent();
                $this->editingRowId = null;

                Notification::make()->title(__('layup::notifications.row_updated'))->success()->duration(2000)->send();
            });
    }

    // ─── Column Settings Slideover ───────────────────────────

    public function editColumn(string $rowId, string $columnId): void
    {
        $this->editingRowId = $rowId;
        $this->editingColumnId = $columnId;

        $row = collect($this->pageContent['rows'])->firstWhere('id', $rowId);
        $col = collect($row['columns'] ?? [])->firstWhere('id', $columnId);

        $this->columnSettings = array_merge(
            $col['settings'] ?? [],
            ['span' => $col['span'] ?? ['sm' => 12, 'md' => 6, 'lg' => 6, 'xl' => 6]],
        );

        $this->mountAction('editColumnAction');
    }

    public function editColumnAction(): Action
    {
        return Action::make('editColumnAction')
            ->label(__('layup::resource.column_settings'))
            ->slideOver()
            ->fillForm(fn (): array => $this->columnSettings)
            ->schema(Column::getFormSchema())
            ->action(function (array $data): void {
                $this->refreshContent();
                $this->pageContent['rows'] = collect($this->pageContent['rows'])->map(function (array $row) use ($data): array {
                    if ($row['id'] !== $this->editingRowId) {
                        return $row;
                    }

                    $row['columns'] = collect($row['columns'])->map(function (array $col) use ($data): array {
                        if ($col['id'] !== $this->editingColumnId) {
                            return $col;
                        }

                        $col['span'] = $data['span'] ?? $col['span'];
                        unset($data['span']);
                        $col['settings'] = $data;

                        return $col;
                    })->all();

                    return $row;
                })->all();

                $this->record->update(['content' => $this->pageContent]);
                $this->syncContent();
                $this->editingRowId = null;
                $this->editingColumnId = null;

                Notification::make()->title(__('layup::notifications.column_updated'))->success()->duration(2000)->send();
            });
    }

    // ─── Widget Operations ───────────────────────────────────

    public function addWidget(string $rowId, string $columnId, string $widgetType): void
    {
        $registry = app(WidgetRegistry::class);
        $defaults = $registry->getDefaultData($widgetType);
        $data = $registry->fireOnCreate($widgetType, $defaults);

        $widget = [
            'id' => 'widget_' . Str::random(8),
            'type' => $widgetType,
            'data' => $data,
        ];

        foreach ($this->pageContent['rows'] as &$row) {
            foreach ($row['columns'] as &$col) {
                if ($col['id'] === $columnId) {
                    $col['widgets'][] = $widget;
                    break 2;
                }
            }
        }

        $this->record->update(['content' => $this->pageContent]);
        $this->syncContent();
    }

    public function addWidgetAt(string $rowId, string $columnId, string $widgetType, int $position): void
    {
        $registry = app(WidgetRegistry::class);
        $defaults = $registry->getDefaultData($widgetType);
        $data = $registry->fireOnCreate($widgetType, $defaults);

        $widget = [
            'id' => 'widget_' . Str::random(8),
            'type' => $widgetType,
            'data' => $data,
        ];

        foreach ($this->pageContent['rows'] as &$row) {
            if ($row['id'] !== $rowId) {
                continue;
            }
            foreach ($row['columns'] as &$col) {
                if ($col['id'] === $columnId) {
                    array_splice($col['widgets'], $position, 0, [$widget]);
                    break 2;
                }
            }
        }

        $this->record->update(['content' => $this->pageContent]);
        $this->syncContent();
    }

    public function editWidget(string $rowId, string $columnId, string $widgetId): void
    {
        $this->editingRowId = $rowId;
        $this->editingColumnId = $columnId;
        $this->editingWidgetId = $widgetId;

        $row = collect($this->pageContent['rows'])->firstWhere('id', $rowId);
        $col = collect($row['columns'])->firstWhere('id', $columnId);
        $widget = collect($col['widgets'])->firstWhere('id', $widgetId);

        $this->editingWidgetType = $widget['type'];
        $this->widgetData = $widget['data'] ?? [];

        $this->mountAction('editWidgetAction');
    }

    public function editWidgetAction(): Action
    {
        $registry = app(WidgetRegistry::class);

        return Action::make('editWidgetAction')
            ->label(__('layup::resource.edit_widget'))
            ->slideOver()
            ->fillForm(fn (): array => $this->widgetData)
            ->schema(fn () => $registry->getFormSchema($this->editingWidgetType ?? 'text'))
            ->action(function (array $data): void {
                $this->refreshContent();
                $registry = app(WidgetRegistry::class);
                $data = $registry->fireOnSave($this->editingWidgetType, $data);

                $this->pageContent['rows'] = collect($this->pageContent['rows'])->map(function (array $row) use ($data): array {
                    if ($row['id'] !== $this->editingRowId) {
                        return $row;
                    }

                    $row['columns'] = collect($row['columns'])->map(function (array $col) use ($data): array {
                        if ($col['id'] !== $this->editingColumnId) {
                            return $col;
                        }

                        $col['widgets'] = collect($col['widgets'])->map(function (array $widget) use ($data): array {
                            if ($widget['id'] === $this->editingWidgetId) {
                                $widget['data'] = $data;
                            }

                            return $widget;
                        })->all();

                        return $col;
                    })->all();

                    return $row;
                })->all();

                $this->record->update(['content' => $this->pageContent]);
                $this->syncContent();

                $this->editingRowId = null;
                $this->editingColumnId = null;
                $this->editingWidgetId = null;
                $this->editingWidgetType = null;

                // Validate full content and warn if issues
                $result = (new ContentValidator)->validate($this->pageContent);
                if (! $result->passes()) {
                    Notification::make()
                        ->title(__('layup::notifications.widget_saved_with_warnings'))
                        ->body(implode("\n", array_slice($result->errors(), 0, 3)))
                        ->warning()
                        ->duration(5000)
                        ->send();
                } else {
                    Notification::make()->title(__('layup::notifications.widget_updated'))->success()->duration(2000)->send();
                }
            });
    }

    public function confirmDeleteWidget(string $rowId, string $columnId, string $widgetId): void
    {
        $this->editingRowId = $rowId;
        $this->editingColumnId = $columnId;
        $this->editingWidgetId = $widgetId;
        $this->mountAction('deleteWidgetAction');
    }

    public function updateWidgetContent(string $rowId, string $columnId, string $widgetId, string $content): void
    {
        $this->refreshContent();

        foreach ($this->pageContent['rows'] as &$row) {
            if ($row['id'] !== $rowId) {
                continue;
            }
            foreach ($row['columns'] as &$col) {
                if ($col['id'] !== $columnId) {
                    continue;
                }
                foreach ($col['widgets'] as &$widget) {
                    if ($widget['id'] === $widgetId) {
                        $widget['data']['content'] = $content;
                        break 3;
                    }
                }
            }
        }

        $this->record->update(['content' => $this->pageContent]);
        $this->syncContent();

        Notification::make()->title(__('layup::notifications.content_updated'))->success()->duration(2000)->send();
    }

    public function updateContent(array $content): void
    {
        $result = (new ContentValidator)->validate($content);

        if (! $result->passes()) {
            Notification::make()
                ->title(__('layup::notifications.invalid_content'))
                ->body(implode(' ', $result->errors()))
                ->danger()
                ->send();

            return;
        }

        $this->pageContent = $content;
        $this->record->update(['content' => $this->pageContent]);
        $this->syncContent();
    }

    public function deleteWidgetAction(): Action
    {
        return Action::make('deleteWidgetAction')
            ->label(__('layup::resource.delete_widget'))
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('layup::resource.delete_widget'))
            ->modalDescription(__('layup::resource.delete_widget_description'))
            ->action(function (): void {
                $this->refreshContent();
                $registry = app(WidgetRegistry::class);

                foreach ($this->pageContent['rows'] as &$row) {
                    foreach ($row['columns'] as &$col) {
                        if ($col['id'] === $this->editingColumnId) {
                            $widget = collect($col['widgets'])->firstWhere('id', $this->editingWidgetId);
                            if ($widget) {
                                $registry->fireOnDelete($widget['type'], $widget['data'] ?? []);
                            }
                            $col['widgets'] = collect($col['widgets'])
                                ->reject(fn ($w): bool => $w['id'] === $this->editingWidgetId)
                                ->values()
                                ->all();
                            break 2;
                        }
                    }
                }

                $this->record->update(['content' => $this->pageContent]);
                $this->syncContent();
                $this->editingRowId = null;
                $this->editingColumnId = null;
                $this->editingWidgetId = null;

                Notification::make()->title(__('layup::notifications.widget_deleted'))->success()->duration(2000)->send();
            });
    }

    // ─── Properties for Alpine ───────────────────────────────

    public function getWidgetRegistryProperty(): array
    {
        return app(WidgetRegistry::class)->toJs();
    }

    public function getBreakpointsProperty(): array
    {
        return config('layup.breakpoints', []);
    }

    public function getRowTemplatesProperty(): array
    {
        return config('layup.row_templates', []);
    }

    public function getDefaultBreakpointProperty(): string
    {
        return config('layup.default_breakpoint', 'lg');
    }

    public function getTranslationsProperty(): array
    {
        return [
            'saving' => __('layup::builder.saving'),
            'saved' => __('layup::builder.saved'),
            'row_label' => __('layup::builder.row_label'),
            'categories' => [
                'content' => __('layup::widgets.categories.content'),
                'media' => __('layup::widgets.categories.media'),
                'interactive' => __('layup::widgets.categories.interactive'),
                'layout' => __('layup::widgets.categories.layout'),
                'advanced' => __('layup::widgets.categories.advanced'),
            ],
        ];
    }
}
