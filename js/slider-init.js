/*
 * 범용 슬라이드 컴포넌트(<x-slider>) 초기화 스크립트.
 * frontend.js와는 완전히 별도 파일 — Swiper 본체(vendor/swiper)에 의존하며,
 * 페이지 안의 [data-slider] 요소를 각각 독립된 Swiper 인스턴스로 초기화한다.
 */
(function () {
    'use strict';

    function initSlider(el) {
        var prefersReducedMotion = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        var autoplayEnabled = el.dataset.autoplay === 'true' && !prefersReducedMotion;
        var interval = parseInt(el.dataset.interval, 10) || 5000;
        var loop = el.dataset.loop === 'true';
        var paginationType = el.dataset.pagination || 'none';
        var slidesDesktop = parseFloat(el.dataset.slidesDesktop) || 1;
        var slidesTablet = parseFloat(el.dataset.slidesTablet) || 1;
        var slidesMobile = parseFloat(el.dataset.slidesMobile) || 1;
        var gap = parseInt(el.dataset.gap, 10) || 16;

        var paginationEl = el.querySelector('.lf-slider-pagination');
        var prevEl = el.querySelector('.lf-slider-prev');
        var nextEl = el.querySelector('.lf-slider-next');
        var toggleEl = el.querySelector('.lf-slider-toggle');

        var config = {
            speed: prefersReducedMotion ? 0 : 400,
            loop: loop,
            spaceBetween: gap,
            slidesPerView: slidesMobile,
            slidesPerGroup: 1,
            // 몇 장씩 보이든(1/3/6...) 넘어가는 건 항상 한 칸씩만 이동하도록 slidesPerGroup을 고정한다.
            breakpoints: {
                577: { slidesPerView: slidesTablet, slidesPerGroup: 1, spaceBetween: gap },
                993: { slidesPerView: slidesDesktop, slidesPerGroup: 1, spaceBetween: gap },
            },
            keyboard: { enabled: true, onlyInViewport: true },
            a11y: {
                enabled: true,
                prevSlideMessage: '이전 슬라이드',
                nextSlideMessage: '다음 슬라이드',
                firstSlideMessage: '첫 슬라이드입니다',
                lastSlideMessage: '마지막 슬라이드입니다',
                paginationBulletMessage: '{{index}}번째 슬라이드로 이동',
                slideLabelMessage: '{{index}} / {{slidesLength}}',
                containerRoleDescriptionMessage: '캐러셀',
                itemRoleDescriptionMessage: '슬라이드',
            },
        };

        if (paginationEl && paginationType !== 'none') {
            config.pagination = {
                el: paginationEl,
                clickable: true,
            };

            if (paginationType === 'numbers') {
                config.pagination.renderBullet = function (index, className) {
                    return '<button type="button" class="' + className + '">' + (index + 1) + '</button>';
                };
            }

            if (paginationType === 'fraction') {
                // Swiper 기본 fraction 타입 — "01 / 03"처럼 앞자리 0을 채운 형태로 표시.
                config.pagination.type = 'fraction';
                config.pagination.formatFractionCurrent = function (n) { return String(n).padStart(2, '0'); };
                config.pagination.formatFractionTotal = function (n) { return String(n).padStart(2, '0'); };
            }
        }

        if (prevEl && nextEl) {
            config.navigation = { prevEl: prevEl, nextEl: nextEl };
        }

        if (autoplayEnabled) {
            config.autoplay = {
                delay: interval,
                disableOnInteraction: false,
                pauseOnMouseEnter: true,
            };
        }

        var swiper = new Swiper(el, config);

        if (toggleEl && swiper.autoplay) {
            // 마우스오버로 인한 일시정지와 사용자가 직접 누른 일시정지를 구분해야
            // 마우스가 벗어났을 때 의도치 않게 재생되지 않는다.
            var manuallyPaused = false;

            toggleEl.addEventListener('click', function () {
                if (manuallyPaused) {
                    swiper.autoplay.start();
                    manuallyPaused = false;
                    toggleEl.dataset.state = 'playing';
                    toggleEl.setAttribute('aria-label', '일시정지');
                } else {
                    swiper.autoplay.stop();
                    manuallyPaused = true;
                    toggleEl.dataset.state = 'paused';
                    toggleEl.setAttribute('aria-label', '재생');
                }
            });

            el.addEventListener('focusin', function () {
                swiper.autoplay.stop();
            });

            el.addEventListener('focusout', function () {
                if (!manuallyPaused) {
                    swiper.autoplay.start();
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-slider]').forEach(initSlider);
    });
})();
