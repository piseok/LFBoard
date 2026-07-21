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

    document.querySelectorAll('.site-popup').forEach(function (popup) {
        var id = popup.getAttribute('data-popup-id');
        if (id && ! hasHideCookie(id)) {
            popup.style.display = 'block';
        }
    });

    document.querySelectorAll('.popup-close-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var popup = btn.closest('.site-popup');
            if (popup) {
                popup.style.display = 'none';
            }
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

            if (popup) {
                popup.style.display = 'none';
            }
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
})();
