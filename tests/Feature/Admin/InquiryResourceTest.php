<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Inquiries\Pages\ListInquiries;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// 목록의 '접수일'(created_at) 컬럼에 정렬 기능이 빠져 있던 회귀 테스트.
// ->defaultSort('created_at', 'desc')가 있다고 해서 컬럼 헤더 클릭 정렬(->sortable())까지
// 저절로 되는 게 아니다 — sortable이 없으면 Filament는 sortTable() 요청 자체를 무시하고
// (테이블 쿼리에 created_at으로 orderBy를 걸지 않고) id desc로만 정렬하므로, 삽입 순서와
// 접수일 순서가 다른 레코드를 만들어 실제 정렬 결과로 검증한다.
class InquiryResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_at_column_can_be_sorted_ascending(): void
    {
        $admin = User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        // 삽입 순서(id 1,2,3)와 접수일 순서가 어긋나도록 만든다.
        $third = Inquiry::create(['name' => '세번째로 등록', 'title' => '제목', 'content' => '내용']);
        $third->forceFill(['created_at' => now()->subDays(1)])->save();

        $first = Inquiry::create(['name' => '첫번째로 등록', 'title' => '제목', 'content' => '내용']);
        $first->forceFill(['created_at' => now()->subDays(3)])->save();

        $second = Inquiry::create(['name' => '두번째로 등록', 'title' => '제목', 'content' => '내용']);
        $second->forceFill(['created_at' => now()->subDays(2)])->save();

        Livewire::actingAs($admin)->test(ListInquiries::class)
            ->sortTable('created_at', 'asc')
            ->assertCanSeeTableRecords([$first, $second, $third], inOrder: true);
    }

    // '응답 소요시간' 컬럼 — replied_at이 없는 건은 '미답변' placeholder로 표시된다.
    public function test_response_time_column_is_blank_when_not_replied(): void
    {
        $admin = User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        $inquiry = Inquiry::create(['name' => '미답변', 'title' => '제목', 'content' => '내용']);

        Livewire::actingAs($admin)->test(ListInquiries::class)
            ->assertTableColumnStateSet('response_time', null, record: $inquiry);
    }

    // 접수부터 답변까지 2일 3시간이 걸린 경우 "2일 3시간"으로 축약 표시된다.
    public function test_response_time_column_shows_days_and_hours(): void
    {
        $this->freezeTime();

        $admin = User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        $repliedAt = now();
        $createdAt = $repliedAt->copy()->subDays(2)->subHours(3);

        $inquiry = Inquiry::create([
            'name' => '답변완료', 'title' => '제목', 'content' => '내용',
            'replied_at' => $repliedAt,
        ]);
        $inquiry->forceFill(['created_at' => $createdAt])->save();

        Livewire::actingAs($admin)->test(ListInquiries::class)
            ->assertTableColumnStateSet('response_time', '2일 3시간', record: $inquiry);
    }

    // 30분 만에 답변한 경우 "30분"으로 표시된다.
    public function test_response_time_column_shows_minutes_for_quick_reply(): void
    {
        $this->freezeTime();

        $admin = User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        $repliedAt = now();
        $createdAt = $repliedAt->copy()->subMinutes(30);

        $inquiry = Inquiry::create([
            'name' => '빠른답변', 'title' => '제목', 'content' => '내용',
            'replied_at' => $repliedAt,
        ]);
        $inquiry->forceFill(['created_at' => $createdAt])->save();

        Livewire::actingAs($admin)->test(ListInquiries::class)
            ->assertTableColumnStateSet('response_time', '30분', record: $inquiry);
    }
}
