// 게시판 검색폼에서 "검색 조건"(search_type)으로 커스텀필드를 고르면, 그 필드가
// select/radio/checkbox 타입일 때는 자유 텍스트 대신 그 필드의 선택지와 정확히 일치하는
// <select name="q">로 바꿔치기한다. 두 종류 입력 모두 처음부터 DOM에 렌더링해두고
// disabled/hidden을 토글하는 방식이라(제출 시 disabled=미전송), name="q"가 두 번 겹쳐 제출되는 일이 없다.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-custom-search]').forEach(function (form) {
        var typeSelect = form.querySelector('.board-search-type');
        var defaultInput = form.querySelector('.board-search-value-default');
        var optionSelects = form.querySelectorAll('.board-search-value-option');

        if (!typeSelect || !defaultInput) {
            return;
        }

        function sync() {
            var selectedType = typeSelect.value;
            var matched = null;

            optionSelects.forEach(function (select) {
                if (select.dataset.searchFor === selectedType) {
                    matched = select;
                }
            });

            optionSelects.forEach(function (select) {
                var isMatch = select === matched;
                select.disabled = !isMatch;
                select.hidden = !isMatch;
            });

            defaultInput.disabled = !!matched;
            defaultInput.hidden = !!matched;
        }

        typeSelect.addEventListener('change', sync);
        sync();
    });
});
