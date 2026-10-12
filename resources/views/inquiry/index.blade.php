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
        {{--

            261002 비회원이 게시글을 다시 볼 상황이 많지 않지만 횩여나 다시 확인하려 할때
            기존에 접수번호 안내 등은 비회원에게는 맞지 않는 상황이라 처음 문의글을 접수하였을때의 링크와 비밀번호를 기억하고 메일로도 등록과 답변메일이 간다는 것을 안내하는 문구로 변경.

        --}}
        <div class="no_member_text">
            <div class="txt-box">
                <p>
                    비회원은 상담 내역을 다시 확인할 수 있도록 <br>
                    접수 완료 후 표시되는 상세페이지 주소와 작성 시 입력한 비밀번호를 보관해 주세요.
                </p>
                <p class="hint">{{ __('링크를 분실하면 사이트에서 상담 내역을 다시 찾을 수 없습니다.') }}</p>
                <p class="hint">{{ __('이메일을 입력하신 경우 문의 접수 및, 답변 내용은 해당 이메일로도 보내드립니다.') }}</p>
            </div>

            <div class="form-actions">
                <a href="{{ front_route('inquiry.create') }}" class="btn btn-primary">{{ __('글쓰기') }}</a>
            </div>
        </div>
    @endauth
@endsection
