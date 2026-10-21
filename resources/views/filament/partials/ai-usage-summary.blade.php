{{--
    AI 비서 "오늘 사용량" 요약(읽기 전용) - 사이트 설정 > AI 연동 탭과 AI 비서 사용 내역 화면이 같이 쓴다.
    ai_chat_usage 표로만 계산한다(제공자 API 를 부르지 않는다). 제공자 쪽 크레딧 잔액은 조회 수단이 없어 보여 주지 않는다 -
    "우리 사이트의 일일 한도 기준"이다. 스타일은 public/css/ai-chat.css 의 .aic-usage-table(토큰 색, 라이트/다크).
--}}
@php
    $chatLimit = $usage->limit('chat', 'site');
    $imageLimit = $usage->limit('image', 'site');
@endphp
<div class="aic-usage-summary">
    <p class="aic-usage-summary__lead">
        우리 사이트의 일일 한도 기준 · 사이트 전체 오늘 질문 <strong>{{ $summary['chat_total'] }}/{{ $chatLimit }}회</strong>,
        이미지 <strong>{{ $summary['image_total'] }}/{{ $imageLimit }}장</strong> (매일 0시에 초기화)
    </p>

    @if ($summary['rows'] === [])
        <p class="aic-usage-summary__empty">오늘은 아직 AI 비서를 사용한 관리자가 없습니다.</p>
    @else
        <table class="aic-usage-table">
            <caption class="aic-sr-only">오늘 관리자별 AI 비서 사용량</caption>
            <thead>
                <tr>
                    <th scope="col">관리자</th>
                    <th scope="col">질문(회)</th>
                    <th scope="col">이미지(장)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['rows'] as $row)
                    <tr>
                        <th scope="row">{{ $row['name'] }}</th>
                        <td>{{ $row['chat'] }}</td>
                        <td>{{ $row['image'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
