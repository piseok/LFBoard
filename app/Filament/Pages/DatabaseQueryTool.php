<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresSuperAdmin;
use App\Services\AdminAuditLogService;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Throwable;
use UnitEnum;

// phpMyAdmin 없이도 최고관리자가 직접 SQL을 실행할 수 있게 하는 도구(공유호스팅마다 phpMyAdmin
// 제공 여부/버전이 달라 이걸 붙이는 대신, MySQL/MariaDB 버전과 무관하게 항상 동작하도록 Laravel
// 자체 DB 연결로 직접 구현). SELECT/INSERT/UPDATE/DELETE 등 대부분의 쿼리를 실행할 수 있고
// 최고관리자 본인 책임하에 쓰는 기능이라 쿼리 종류 자체를 제한하지는 않되, 실행 내역은 전부
// 관리자 활동 로그(action='query')에 남겨 사고 시 추적할 수 있게 한다.
class DatabaseQueryTool extends Page
{
    use RequiresSuperAdmin;

    protected string $view = 'filament.pages.database-query-tool';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $navigationLabel = '데이터베이스 조회';

    protected static string|UnitEnum|null $navigationGroup = '시스템 설정';

    protected static ?int $navigationSort = 85;

    protected static ?string $title = '데이터베이스 조회';

    public ?array $data = ['sql' => ''];

    public ?string $resultHtml = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('sql')->label('SQL 쿼리')
                    ->required()
                    ->rows(8)
                    ->placeholder('SELECT * FROM users LIMIT 10;')
                    ->extraInputAttributes(['style' => 'font-family: monospace; font-size: 0.85rem;'])
                    ->helperText('SELECT/INSERT/UPDATE/DELETE 등 대부분의 쿼리를 실행할 수 있습니다. 실행 즉시 반영되며 되돌릴 수 없으니 반드시 백업 후 사용하세요. 모든 실행 내역(성공/실패, 영향받은 행 수 포함)은 관리자 활동 로그에 남습니다.'),
            ])
            ->statePath('data');
    }

    public function run(): void
    {
        $data = $this->form->getState();
        $sql = trim((string) ($data['sql'] ?? ''));

        $this->resultHtml = null;
        $this->errorMessage = null;

        if ($sql === '') {
            return;
        }

        $admin = auth()->user();
        $isReadQuery = (bool) preg_match('/^(select|show|explain|desc)\b/i', $sql);

        try {
            if ($isReadQuery) {
                $rows = DB::select($sql);
                $this->resultHtml = $this->renderRows($rows);
                app(AdminAuditLogService::class)->recordQuery($admin, $sql, true, null, count($rows));

                Notification::make()->title('조회 완료 ('.count($rows).'건)')->success()->send();
            } else {
                $affected = DB::affectingStatement($sql);
                app(AdminAuditLogService::class)->recordQuery($admin, $sql, true, null, $affected);

                Notification::make()->title("실행 완료 ({$affected}행 영향)")->success()->send();
            }
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
            app(AdminAuditLogService::class)->recordQuery($admin, $sql, false, $e->getMessage());

            Notification::make()->title('쿼리 실행 실패')->body($e->getMessage())->danger()->send();
        }
    }

    /**
     * @param  array<int, object>  $rows
     */
    private function renderRows(array $rows): string
    {
        if (empty($rows)) {
            return '<p>결과가 없습니다.</p>';
        }

        $columns = array_keys((array) $rows[0]);
        $displayRows = array_slice($rows, 0, 500);

        $thead = '<tr>'.implode('', array_map(
            fn (string $col) => '<th style="text-align:left;padding:6px 10px;border-bottom:2px solid #e5e7eb;white-space:nowrap;">'.e($col).'</th>',
            $columns
        )).'</tr>';

        $tbody = '';
        foreach ($displayRows as $row) {
            $tbody .= '<tr>';
            foreach ((array) $row as $value) {
                $tbody .= '<td style="padding:6px 10px;border-bottom:1px solid #f3f4f6;white-space:nowrap;">'.e($value === null ? 'NULL' : (string) $value).'</td>';
            }
            $tbody .= '</tr>';
        }

        $truncatedNotice = count($rows) > 500
            ? '<p style="margin-top:8px;color:#92400e;">전체 '.count($rows).'건 중 500건만 표시합니다.</p>'
            : '';

        return '<div style="overflow-x:auto;"><table style="border-collapse:collapse;font-size:0.85rem;width:100%;">'
            .'<thead>'.$thead.'</thead><tbody>'.$tbody.'</tbody></table></div>'.$truncatedNotice;
    }
}
