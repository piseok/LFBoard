@extends('layouts.subpage')

@section('subcontent')
    <x-sub-header :title="__('1:1 상담')" />

    @auth
        <div class="board-toolbar">
            <form method="GET" action="{{ front_route('inquiry.index') }}" class="board-search-form">
                <label for="q" class="sr-only">{{ __('제목 검색') }}</label>
                <input type="text" id="q" name="q" value="{{ request('q') }}" placeholder="{{ __('제목으로 검색') }}">
                <button type="submit" class="btn">{{ __('검색') }}</button>
            </form>
            <a href="{{ front_route('inquiry.create') }}" class="btn btn-primary">{{ __('글쓰기') }}</a>
        </div>

        <table class="board-list">
            <caption class="sr-only">{{ __('나의 상담 내역') }}</caption>
            <thead>
                <tr>
                    <th scope="col" class="col-num">{{ __('번호') }}</th>
                    <th scope="col">{{ __('제목') }}</th>
                    <th scope="col">{{ __('카테고리') }}</th>
                    <th scope="col">{{ __('상태') }}</th>
                    <th scope="col" class="col-date">{{ __('접수일') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($inquiries as $inquiry)
                    <tr>
                        <td class="col-num">{{ $inquiries->total() - (($inquiries->currentPage() - 1) * $inquiries->perPage()) - $loop->index }}</td>
                        <td><a href="{{ front_route('inquiry.show', $inquiry->id) }}">{{ $inquiry->title }}</a></td>
                        <td>{{ $inquiry->category ?: '-' }}</td>
                        <td>
                            <span class="badge {{ $inquiry->status === 'done' ? 'badge-notice' : 'badge-secret' }}">
                                {{ __(['pending' => '대기', 'processing' => '처리중', 'done' => '완료'][$inquiry->status] ?? $inquiry->status) }}
                            </span>
                        </td>
                        <td class="col-date">{{ local_datetime($inquiry->created_at)->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center; padding: 40px 0; color: var(--color-text-muted);">{{ __('상담 내역이 없습니다.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{ $inquiries->links() }}
    @else
        <p>{{ __('비회원으로 접수하신 상담은 접수 완료 시 안내된 화면(비밀번호 확인)을 통해 확인하실 수 있습니다.') }}</p>
        <p class="hint">{{ __('접수 링크를 분실하셨다면 상담 접수번호와 비밀번호를 확인 후 다시 접속해 주세요.') }}</p>
        <div class="form-actions">
            <a href="{{ front_route('inquiry.create') }}" class="btn btn-primary">{{ __('글쓰기') }}</a>
        </div>
    @endauth
@endsection
