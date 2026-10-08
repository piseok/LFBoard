// 프론트엔드 공통 스크립트 — CSP(script-src)에서 인라인 스크립트를 허용하지 않아도 되도록
// 모든 onclick/onsubmit 인라인 핸들러를 이 파일의 이벤트 위임 방식으로 대체한다.
document.addEventListener('DOMContentLoaded', function () {
    // 하위 메뉴 펼치기/접기 (로컬 서브메뉴(LNB) 전용 — 헤더의 모바일 전체메뉴 아코디언은
    // 파일 하단의 별도 IIFE(header/mobile-menu 모듈)가 담당한다)
    document.querySelectorAll('.submenu-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('aria-controls');
            var target = targetId ? document.getElementById(targetId) : null;
            var expanded = btn.getAttribute('aria-expanded') === 'true';

            btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');

            if (target) {
                target.classList.toggle('is-hidden');
            }
        });
    });

    // 댓글 답글 입력폼 토글
    document.querySelectorAll('.reply-toggle-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-target');
            var target = targetId ? document.getElementById(targetId) : null;

            if (target) {
                target.classList.toggle('is-hidden');
            }
        });
    });

    // 삭제 확인 다이얼로그 (게시글/댓글 삭제 폼)
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (! window.confirm(form.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // 퀵메뉴 맨 위로 이동
    document.querySelectorAll('.scroll-top-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            window.scrollTo({top: 0, behavior: 'smooth'});
        });
    });

    // 팝업: 최초 노출 여부 판단(쿠키 확인), 닫기, "오늘 하루 보지 않기"
    function hasHideCookie(popupId) {
        var needle = 'hide_popup_' + popupId + '=';
        return document.cookie.split('; ').some(function (row) {
            return row.indexOf(needle) === 0;
        });
    }

    function closePopup(popup) {
        if (! popup || ! popup.classList.contains('open') || popup.classList.contains('closing')) {
            return;
        }

        popup.addEventListener('animationend', function onEnd(event) {
            if (event.target !== popup || event.animationName !== 'popup-close') {
                return;
            }

            popup.classList.remove('open', 'closing');
            popup.removeEventListener('animationend', onEnd);
        });

        popup.classList.add('closing');
    }

    document.querySelectorAll('.site-popup').forEach(function (popup) {
        var id = popup.getAttribute('data-popup-id');
        if (id && ! hasHideCookie(id)) {
            popup.classList.add('open');
        }
    });

    document.querySelectorAll('.popup-close-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var popup = btn.closest('.site-popup');
            closePopup(popup);
        });
    });

    document.querySelectorAll('.popup-hide-today').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            if (! checkbox.checked) {
                return;
            }

            var popup = checkbox.closest('.site-popup');
            var id = popup ? popup.getAttribute('data-popup-id') : null;

            if (id) {
                document.cookie = 'hide_popup_' + id + '=1;path=/;max-age=86400';
            }

            closePopup(popup);
        });
    });

    // 게시판 글쓰기: 에디터 사용 게시판이면 self-hosted TinyMCE(GPL, API 키 불필요)를 초기화한다.
    // public/js/vendor/tinymce에 미리 받아둔 파일만 쓰며 외부 CDN에 의존하지 않는다.
    document.querySelectorAll('textarea.tinymce-editor').forEach(function (el) {
        if (typeof tinymce === 'undefined') {
            return;
        }

        var baseUrl = el.getAttribute('data-tinymce-base');
        var uploadUrl = el.getAttribute('data-image-upload-url') || '';
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

        // 업로드 XHR 공용 함수: 붙여넣기/드래그(자동 업로드)와 툴바의 커스텀 업로드 버튼이 함께 사용한다.
        function uploadImageFile(file, filename) {
            return new Promise(function (resolve, reject) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', uploadUrl);
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                xhr.onload = function () {
                    var json;
                    try {
                        json = JSON.parse(xhr.responseText);
                    } catch (e) {
                        reject('업로드 응답을 처리할 수 없습니다.');
                        return;
                    }
                    if (xhr.status !== 200 || ! json.location) {
                        reject(json.error || '이미지 업로드에 실패했습니다.');
                        return;
                    }
                    resolve(json.location);
                };
                xhr.onerror = function () {
                    reject('업로드 중 네트워크 오류가 발생했습니다.');
                };
                var formData = new FormData();
                formData.append('file', file, filename);
                xhr.send(formData);
            });
        }

        var config = {
            selector: '#' + el.id,
            license_key: 'gpl', // self-hosted GPL 사용 동의 — TinyMCE 6+에서 이 값이 없으면 에디터가 비활성화됨
            base_url: baseUrl,
            suffix: '.min',
            language: 'ko_KR',
            language_url: baseUrl + '/langs/ko_KR.js',
            height: 420,
            menubar: false,
            branding: false,
            promotion: false,
            // 기본 제공되는 'image' 플러그인 대신, 업로드 전용 커스텀 버튼(imageupload)만 사용한다.
            // TinyMCE 기본 이미지 다이얼로그는 "일반"(URL 직접 입력) 탭과 "업로드" 탭이 함께 있는데,
            // 외부 URL을 직접 붙여넣는 경로를 없애고 업로드만 가능하도록 하기 위함이다.
            plugins: 'lists link table code autolink wordcount fullscreen advlist',
            toolbar: 'undo redo | blocks | bold italic underline strikethrough | ' +
                'alignleft aligncenter alignright | bullist numlist | link' +
                (uploadUrl ? ' imageupload' : '') + ' table | code fullscreen',
            setup: function (editor) {
                // TinyMCE는 iframe 내부에서 편집되므로, 원본 textarea 값은 change 시점마다 동기화해 둔다.
                editor.on('change input undo redo', function () {
                    editor.save();
                });

                if (uploadUrl) {
                    editor.ui.registry.addButton('imageupload', {
                        icon: 'image',
                        tooltip: '이미지 업로드',
                        onAction: function () {
                            var input = document.createElement('input');
                            input.type = 'file';
                            input.accept = 'image/*';
                            input.onchange = function () {
                                var file = input.files[0];
                                if (! file) {
                                    return;
                                }
                                uploadImageFile(file, file.name).then(function (location) {
                                    editor.insertContent('<img src="' + location + '" alt="">');
                                }).catch(function (message) {
                                    editor.notificationManager.open({text: message, type: 'error'});
                                });
                            };
                            input.click();
                        }
                    });
                }
            }
        };

        if (uploadUrl) {
            // 이미지를 본문에 붙여넣거나 끌어다 놓는 경우에도 자동 업로드된다(위 커스텀 버튼과 별개 경로).
            config.automatic_uploads = true;
            config.images_upload_handler = function (blobInfo) {
                return uploadImageFile(blobInfo.blob(), blobInfo.filename());
            };
        }

        tinymce.init(config);
    });

    // 쿠키 동의 배너 — 버튼 클릭 시 동의 여부를 쿠키에 기록하고 새로고침한다(서버 사이드에서
    // Google Analytics 등 비필수 스크립트 삽입 여부를 그 쿠키값으로 판단하므로, 새로고침으로
    // 즉시 반영되게 한다 — 클라이언트에서 별도로 스크립트를 동적으로 끼워 넣지 않아도 됨).
    var cookieConsentBanner = document.querySelector('.cookie-consent');
    if (cookieConsentBanner) {
        cookieConsentBanner.querySelectorAll('[data-consent]').forEach(function (button) {
            button.addEventListener('click', function () {
                var value = button.getAttribute('data-consent');
                var maxAge = 60 * 60 * 24 * 365;
                document.cookie = 'cookie_consent=' + value + '; path=/; max-age=' + maxAge + '; SameSite=Lax';
                window.location.reload();
            });
        });
    }

    // Google Analytics (site_settings에 google_analytics 값이 설정된 경우에만)
    var gaId = document.body.getAttribute('data-ga-id');
    if (gaId) {
        window.dataLayer = window.dataLayer || [];
        window.gtag = function () {
            dataLayer.push(arguments);
        };
        gtag('js', new Date());
        gtag('config', gaId);
    }

    // 메인 화면 작업사례 탭(partials/home/banner-tabs.blade.php) — 탭 버튼 클릭 시 해당
    // 그룹의 패널만 보이게 전환한다. 비활성 패널은 main.css의 .main-cases-panel이
    // display:none으로 완전히 숨기므로, 그 안의 <x-slider>는 페이지 로드 시점엔 폭 0으로
    // 잘못 초기화된다 — 탭이 처음 활성화되는 시점에 swiper.update()로 다시 계산시킨다.
    document.querySelectorAll('[data-cases-tabs]').forEach(function (container) {
        var tabs = container.querySelectorAll('[data-cases-tab]');
        var panels = container.querySelectorAll('[data-cases-panel]');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var key = tab.getAttribute('data-cases-tab');

                tabs.forEach(function (t) {
                    var isActive = t === tab;
                    t.classList.toggle('is-active', isActive);
                    t.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                panels.forEach(function (panel) {
                    var isActive = panel.getAttribute('data-cases-panel') === key;
                    panel.classList.toggle('is-active', isActive);

                    if (isActive) {
                        panel.removeAttribute('inert');

                        var sliderEl = panel.querySelector('[data-slider]');
                        if (sliderEl && sliderEl.swiper) {
                            sliderEl.swiper.update();
                        }
                    } else {
                        panel.setAttribute('inert', '');
                    }
                });
            });
        });
    });
});

// 헤더 / 모바일 전체메뉴 (2026-07 레이아웃) — 스크롤에 따른 헤더 축소/숨김, 모바일 슬라이드
// 패널, 패밀리사이트 드롭다운을 담당한다. "맨 위로" 버튼은 퀵메뉴(partials/quick-menu)의
// .scroll-top-btn이 이미 담당하고 있어 별도로 만들지 않는다(중복 방지).
(function () {
    var header = document.querySelector('.header');
    var dim = document.querySelector('.dim');
    var mobileBtn = document.querySelector('.mobile-btn');
    var mobileMenu = document.querySelector('.mobile-menu');
    var mobileClose = document.querySelector('.mobile-close');
    var familyBtn = document.querySelector('.family-btn');
    var familyList = document.querySelector('.family-list');

    if (! header) {
        return;
    }

    var lastScroll = 0;

    init();

    function init() {
        stickyHeader();
        mobileMenuEvent();
        accordion();
        familySite();
        resizeEvent();
        faqToggler();
    }

    function stickyHeader() {
        window.addEventListener('scroll', function () {
            var top = window.pageYOffset;

            header.classList.toggle('scroll', top > 20);
            header.classList.toggle('hide', top > lastScroll && top > 250);

            lastScroll = top;
        });
    }

    function mobileMenuEvent() {
        if (! mobileBtn || ! mobileMenu) {
            return;
        }

        mobileBtn.addEventListener('click', openMenu);

        if (mobileClose) {
            mobileClose.addEventListener('click', closeMenu);
        }
        if (dim) {
            dim.addEventListener('click', closeMenu);
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeMenu();
            }
        });
    }

    function openMenu() {
        mobileMenu.classList.add('open');
        if (dim) {
            dim.classList.add('show');
        }
        document.body.classList.add('lock');
    }

    function closeMenu() {
        mobileMenu.classList.remove('open');
        if (dim) {
            dim.classList.remove('show');
        }
        document.body.classList.remove('lock');
    }

    // 모바일 전체메뉴 아코디언 — 어느 깊이의 하위 메뉴든(2단/3단) 동작하도록 버튼의 바로 다음
    // 형제 요소(자신의 <ul>)만 토글하고, "다른 항목 닫기"도 같은 부모 <ul> 안의 형제로만 한정한다.
    function accordion() {
        if (! mobileMenu) {
            return;
        }

        mobileMenu.querySelectorAll('button').forEach(function (button) {
            button.addEventListener('click', function () {
                var parent = button.parentNode;
                var sub = button.nextElementSibling;

                if (! sub) {
                    return;
                }

                var siblingList = parent.parentNode;
                siblingList.querySelectorAll(':scope > li').forEach(function (item) {
                    if (item !== parent) {
                        item.classList.remove('active');
                        var target = item.querySelector(':scope > ul');
                        if (target) {
                            target.style.display = 'none';
                        }
                    }
                });

                if (parent.classList.contains('active')) {
                    parent.classList.remove('active');
                    sub.style.display = 'none';
                } else {
                    parent.classList.add('active');
                    sub.style.display = 'block';
                }
            });
        });
    }

    function familySite() {
        if (! familyBtn || ! familyList) {
            return;
        }

        familyBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            familyList.classList.toggle('show');
        });

        document.addEventListener('click', function () {
            familyList.classList.remove('show');
        });
    }

    function resizeEvent() {
        window.addEventListener('resize', function () {
            if (window.innerWidth > 992) {
                closeMenu();
            }
        });
    }

    // faq 토글
    function faqToggler() {
        const $faqList = document.querySelector('.board-faq-list');

        if (!$faqList) return;

        const $faqItems = $faqList.querySelectorAll('.board-faq-item');

        $faqItems.forEach((item, index) => {
            const $question = item.querySelector('.board-faq-question');
            if (index === 0) item.dataset.toggle = 'open';

            if (!$question) return;

            $question.addEventListener('click', () => {
                const isOpen = item.dataset.toggle === 'open';

                $faqItems.forEach((faqItem) => {
                    faqItem.dataset.toggle = 'hidden';
                });

                item.dataset.toggle = isOpen ? 'hidden' : 'open';
            });
        });
    }




    // 스크롤 애니메이션

    // 사용법
    // 1. animationElements에 사용하려는 클래스명을 등록한다.
    // 2. css에 가서 해당 클래스를 선언해서 애니메이션 동작 전 초기상태를 작성.
    // 3. 애니메이션 클래스명 뒤에 .trigger를 붙이고 애니메이션 동작 css를 작성.
    // 4. 애니메이션마다 delay를 다르게 주고 싶으면 애니메이션 클래스를 준 html에
    // data-delay=""속성을 주고 0.5s 이런식으로 작성해준다.

    // 1. 관찰자(Observer) 생성
    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            // 요소가 화면에 뷰포트와 교차(등장)했을 때
            if (entry.isIntersecting) {
                if(entry.target.dataset.delay) {
                    entry.target.style.animationDelay = entry.target.dataset.delay;
                }

                entry.target.classList.add('trigger'); // 애니메이션 클래스 추가
                // 만약 한 번만 실행하고 감지를 멈추고 싶다면
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.2 // 요소가 화면에 20% 보일 때 작동
    });

    // 2. 관찰할 대상(Target)들을 지정하여 관찰 시작
    const animationElements = document.querySelectorAll(
        '.animate-custom, .chars, .fade-top, .box-fade-top, .fade-down, .fade-left, .fade-right, .title-deco, .line-path'
    );

    animationElements.forEach(el => observer.observe(el));

    // 스크롤 애니메이션 end


    // 모든 폼은 실제 제출 후 15초 동안 중복 제출을 막는다.
    function formSubmitLock_hanlder () {
        const formSubmitLocks = new WeakMap();

        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;

            const lockedUntil = formSubmitLocks.get(form) || 0;
            if (Date.now() < lockedUntil) {
                event.preventDefault();
                return;
            }

            // 동의 확인, 삭제 확인, 캡차 등에서 취소된 제출은 잠그지 않는다.
            if (event.defaultPrevented) return;

            formSubmitLocks.set(form, Date.now() + 15000);
            const submitButtons = Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'))
                .filter(function (button) {
                    return button.form === form && !button.disabled;
                });

            const submitButtonStates = submitButtons.map(function (button) {
                return {
                    button: button,
                    isInput: button instanceof HTMLInputElement,
                    hasText: button.textContent.trim() !== '',
                    originalValue: button.value,
                    originalNodes: Array.from(button.childNodes)
                };
            });

            // 클릭한 제출 버튼의 name/value가 전송 데이터에 포함된 뒤 표시를 변경한다.
            window.setTimeout(function () {
                submitButtonStates.forEach(function (state) {
                    state.button.disabled = true;
                    if (state.isInput) {
                        state.button.value = '처리중...';
                    } else {
                        const loader = document.createElement('span');
                        loader.className = 'loader';
                        loader.setAttribute('aria-hidden', 'true');
                        if (state.hasText) {
                            state.button.replaceChildren(loader, document.createTextNode(' 처리중...'));
                        } else {
                            state.button.replaceChildren(loader);
                        }
                    }
                });
            }, 0);

            window.setTimeout(function () {
                formSubmitLocks.delete(form);
                submitButtonStates.forEach(function (state) {
                    if (state.isInput) {
                        state.button.value = state.originalValue;
                    } else {
                        state.button.replaceChildren(...state.originalNodes);
                    }
                    state.button.disabled = false;
                });
            }, 15000);
        });
    }

    formSubmitLock_hanlder();

    // dialog 모달
    (function(){
        const openBtns = document.querySelectorAll('.modal_openBtn');
        const closeBtns = document.querySelectorAll('.modal_closeBtn');
        const dialogs = document.querySelectorAll('dialog[data-modal]');
        const autoCloseTimers = new WeakMap();

        function openDialog(dialog) {
            const autoCloseDelay = Number(dialog.dataset.autoClose);
            window.clearTimeout(autoCloseTimers.get(dialog));
            dialog.showModal();
            autoCloseTimers.set(dialog, window.setTimeout(() => {
                if (dialog.open) dialog.close();
                autoCloseTimers.delete(dialog);
            }, autoCloseDelay));
        }

        dialogs.forEach((dialog) => {
            dialog.addEventListener('close', () => {
                window.clearTimeout(autoCloseTimers.get(dialog));
                autoCloseTimers.delete(dialog);
            });
        });

        // 1. 버튼의 data-modal과 일치하는 모달 열기
        openBtns.forEach((btn) => {
            btn.addEventListener('click', () => {
                const modalName = btn.dataset.modal;
                const dialog = Array.from(dialogs).find(
                    (item) => item.dataset.modal === modalName
                );

                if (dialog && !dialog.open) {
                    openDialog(dialog);
                }
            });
        });

        // 열기 버튼 없이 렌더링된 알림 모달 자동 열기
        dialogs.forEach((dialog) => {
            if (dialog.dataset.autoOpen === 'true' && !dialog.open) {
                openDialog(dialog);
            }
        });

        // 2. 닫기 버튼이 포함된 모달 닫기
        closeBtns.forEach((btn) => {
            btn.addEventListener('click', () => {
                const dialog = btn.closest('dialog');
                const actionUrl = btn.dataset.action;

                if (dialog?.open) {
                    dialog.close();
                }

                if (actionUrl) {
                    try {
                        const targetUrl = new URL(actionUrl, window.location.origin);

                        if (['http:', 'https:'].includes(targetUrl.protocol)) {
                            window.location.assign(targetUrl.href);
                        }
                    } catch (error) {
                        console.warn('올바르지 않은 모달 이동 URL입니다.', actionUrl);
                    }
                }
            });
        });

        // 3. 모달 바깥(backdrop) 클릭 시 닫기
        dialogs.forEach((dialog) => {
            dialog.addEventListener('click', (e) => {
                if (e.target === dialog) {
                    dialog.close();
                }
            });
        });
    })();
})();
