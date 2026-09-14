/*
 * 메인 화면 배너 탭 위젯([data-banner-tabs], partials/home/banner-tabs.blade.php) — 탭 버튼
 * 클릭 시 해당 그룹의 패널만 보이게 전환한다. 각 패널 안의 <x-slider>는 slider-init.js가 이미
 * 별도로 초기화하므로 이 파일은 탭 전환(활성 클래스 + aria-selected + inert)만 담당한다.
 */
(function () {
    'use strict';

    function initBannerTabs(container) {
        var buttons = container.querySelectorAll('[data-banner-tab-btn]');
        var panels = container.querySelectorAll('[data-banner-tab-panel]');

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                var key = button.dataset.bannerTabBtn;

                buttons.forEach(function (btn) {
                    var isActive = btn === button;
                    btn.classList.toggle('is-active', isActive);
                    btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                panels.forEach(function (panel) {
                    var isActive = panel.dataset.bannerTabPanel === key;
                    panel.classList.toggle('is-active', isActive);

                    if (isActive) {
                        panel.removeAttribute('inert');

                        // 비활성 상태(display:none)에서 초기화된 Swiper는 폭을 0으로 계산해
                        // 슬라이드가 어긋난다 — 처음 활성화되는 시점에 강제로 재계산시킨다.
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
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-banner-tabs]').forEach(initBannerTabs);
    });
})();
