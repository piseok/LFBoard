/* AI 비서 보조 스크립트 (2026-10-21, AI 비서 2.0).
 *
 * 하는 일은 하나뿐이다: AI 가 폼 칸을 채웠을 때(작성 화면 페이지가 `ai-field-filled` 브라우저 이벤트를 보낸다)
 * 그 칸을 눈에 띄게 표시한다. 저장은 하지 않으며 서버로 아무것도 보내지 않는다 - 네트워크 호출·폼 제출 코드가 없다
 * (이것은 AiFormFillSafetyTest 의 정적 시험이 고정한다). 사용자가 그 칸을 직접 고치면 표시가 사라진다.
 * 스크립트가 없거나 칸을 못 찾아도 채우기 자체는 서버(Livewire)가 이미 끝낸 상태라 기능에는 영향이 없다.
 * Livewire 가 폼을 다시 그리면 표시 속성이 지워지므로 다시 그릴 때마다 표시를 복원한다. */
(function () {
    'use strict';

    var FIELD_NAME = /^[A-Za-z0-9_]+$/;
    // 지금 "AI 가 채움" 으로 표시해 둔 필드 이름들(사용자가 고치기 전까지).
    var filled = {};

    // 필드 이름으로 Filament 필드 래퍼를 찾는다. 입력 요소의 id 가 ".필드이름" 으로 끝나거나,
    // wire:model/x-model 상태 경로가 data.필드이름 이거나, 리치 에디터처럼 x-data 설정에 상태 경로가 들어 있는 경우.
    function findWrapper(field) {
        var wrappers = document.querySelectorAll('[data-field-wrapper]');
        var statePath = new RegExp('data\\.' + field + '(?![\\w.])');

        for (var i = 0; i < wrappers.length; i++) {
            var wrapper = wrappers[i];

            if (wrapper.querySelector('[id$=".' + field + '"], [id="' + field + '"]')) {
                return wrapper;
            }

            var candidates = wrapper.querySelectorAll('input, textarea, select, [x-data]');

            for (var j = 0; j < candidates.length; j++) {
                var attrs = candidates[j].attributes;

                for (var k = 0; k < attrs.length; k++) {
                    var name = attrs[k].name;

                    if ((name.indexOf('wire:model') === 0 || name === 'x-model' || name === 'x-data') && statePath.test(attrs[k].value)) {
                        return wrapper;
                    }
                }
            }
        }

        return null;
    }

    function apply() {
        Object.keys(filled).forEach(function (field) {
            var wrapper = findWrapper(field);

            if (!wrapper || wrapper.hasAttribute('data-ai-filled')) {
                return;
            }

            wrapper.setAttribute('data-ai-filled', field);

            // 사용자가 직접 고치면 표시를 걷는다(이후 다시 그려져도 복원하지 않는다).
            var clear = function (event) {
                if (event && event.isTrusted === false) {
                    return;
                }

                delete filled[field];
                wrapper.removeAttribute('data-ai-filled');
                wrapper.removeEventListener('input', clear, true);
            };

            wrapper.addEventListener('input', clear, true);
        });
    }

    function mark(field) {
        if (typeof field !== 'string' || !FIELD_NAME.test(field)) {
            return;
        }

        filled[field] = true;

        // 서버가 값을 넣고 화면을 다시 그린 뒤에 찾는다.
        window.setTimeout(function () {
            apply();

            var wrapper = findWrapper(field);

            if (!wrapper) {
                return;
            }

            try {
                wrapper.scrollIntoView({ block: 'center', behavior: 'smooth' });
            } catch (e) {
                /* 스크롤은 편의 기능 - 실패해도 무시 */
            }
        }, 150);
    }

    window.addEventListener('ai-field-filled', function (event) {
        var detail = event && event.detail ? event.detail : {};
        mark(detail.field);
    });

    // Livewire 가 DOM 을 다시 그리면(morph) 래퍼의 data-ai-filled 가 지워진다 - 복원한다.
    document.addEventListener('livewire:init', function () {
        if (window.Livewire && typeof window.Livewire.hook === 'function') {
            window.Livewire.hook('morphed', function () {
                window.setTimeout(apply, 50);
            });
        }
    });
})();
