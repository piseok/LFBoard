{{-- resources/views/components/dialog-modal.blade.php --}}

{{--
    간단한 모달 기능

    사용법: 
        - open 버튼 없이 사용할때는 이렇게 특정값을 받아서 작동
        @if (session('status'))
            <x-dialog-modal
                id="status-modal"
                class-name="status-modal"
                data-model="status-modal"
                data-action="/"
                :auto-open="true"
            />
        @endif

        - open btn 있을 시 open btn 두고 이것만 작성
        <x-dialog-modal
            id="status-modal"
            class-name="status-modal"
            data-model="status-modal"
            data-action="/"
            :auto-open="true"
        />

    data-model 속성은 모달을 여는 버튼과 닫는 버튼 모두 동일한 속성으로 들어가야 제대로 작동합니다.
    data-action은 link 이동이 필요할때 쓰입니다.
    오픈 버튼 클래스는 .open_modal_openBtn, 닫는 버튼은 전부 .modal_closeBtn 이 클래스들을 달아주고 data-model 값을 modal 본체와 일치시켜야 합니다.

    작동하는 코드는 frontend.js dialog 모달 주석 검색
--}}

@props([
    'id',
    'className' => '',
    'maxWidth' => '330px',
    'dataModel' => null,
    'dataAction' => null,
    'autoOpen' => false,
    'autoClose' => '3000',
    'content' => '기본 내용을 입력해 주세요.'
])



<dialog id="{{ $id }}" class="{{ $className }} confirm" role="status" style="max-width: {{ $maxWidth }};" data-modal="{{ $dataModel ?: $id }}" data-auto-close="{{ $autoClose }}" data-auto-open="{{ $autoOpen ? 'true' : 'false' }}">
    <div class="modal-header">
        <h5>알림</h5>
        <button type="button" class="modal_closeBtn btn" data-modal="{{ $dataModel ?: $id }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="auto" height="auto" viewBox="0 0 8 8" fill="none">
            <path d="M7.28065 0.209117C7.41461 0.343072 7.48986 0.524754 7.48986 0.714194C7.48986 0.903634 7.41461 1.08532 7.28065 1.21927L4.75527 3.74465L7.28065 6.27003C7.41461 6.40399 7.48986 6.58567 7.48986 6.77511C7.48986 6.96455 7.41461 7.14623 7.28065 7.28018C7.1467 7.41414 6.96501 7.48939 6.77557 7.48939C6.58613 7.48939 6.40445 7.41414 6.2705 7.28019L3.74512 4.7548L1.21974 7.28019C1.08578 7.41414 0.9041 7.48939 0.71466 7.48939C0.525219 7.48939 0.343538 7.41414 0.209584 7.28018C0.0756292 7.14623 0.000374145 6.96455 0.000374482 6.77511C0.000374145 6.58567 0.0756292 6.40399 0.209583 6.27003L2.73496 3.74465L0.209584 1.21927C0.075629 1.08532 0.000374124 0.903634 0.000374113 0.714194C0.000374097 0.524754 0.0756289 0.343072 0.209584 0.209117C0.343538 0.075163 0.525219 -9.17522e-05 0.71466 -9.17364e-05C0.9041 -9.17259e-05 1.08578 0.0751631 1.21974 0.209118L3.74512 2.7345L6.2705 0.209118C6.40445 0.0751631 6.58613 -9.17259e-05 6.77557 -9.17364e-05C6.96502 -9.17522e-05 7.1467 0.075163 7.28065 0.209117Z" fill="currentColor"/>
            </svg>
        </button>
    </div>

    <div class="modal-body">
        {{ $content }}
    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-basic modal_closeBtn" data-modal="{{ $dataModel ?: $id }}" @if ($dataAction) data-action="{{ $dataAction }}" @endif>확인</button>
    </div>
</dialog>
