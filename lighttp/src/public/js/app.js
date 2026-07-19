/* Lighttp v1.0.9 - trxrg.com 深色 Notion 风格 JavaScript */
"use strict";

document.addEventListener('DOMContentLoaded', function() {

    var toggle = document.getElementById('menuToggle');
    var nav = document.getElementById('navLinks');

    if (toggle && nav) {
        // 点击按钮切换菜单
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            var isOpen = nav.classList.contains('open');
            toggle.classList.toggle('active');
            nav.classList.toggle('open');
            document.body.style.overflow = isOpen ? '' : 'hidden';
        });

        // 点击菜单项关闭
        nav.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                toggle.classList.remove('active');
                nav.classList.remove('open');
                document.body.style.overflow = '';
            });
        });
    }

    // 点击外部关闭菜单
    document.addEventListener('click', function(e) {
        if (nav && nav.classList.contains('open')) {
            var isInside = nav.contains(e.target) || toggle.contains(e.target);
            if (!isInside) {
                toggle.classList.remove('active');
                nav.classList.remove('open');
                document.body.style.overflow = '';
            }
        }
    });

    // ===== 禁止触摸滑动打开菜单 =====
    // 阻止页面水平滑动（防止菜单被触摸滑动触发）
    document.addEventListener('touchmove', function(e) {
        // 如果菜单是关闭状态，禁止水平滑动
        if (nav && !nav.classList.contains('open')) {
            var touchX = e.touches[0].clientX;
            // 如果触摸起始位置在屏幕左边缘附近，阻止默认行为
            // 但更安全的方式是直接阻止所有水平滑动
            e.preventDefault();
        }
    }, { passive: false });

    // 更精准的方式：只阻止向右滑动（在菜单关闭时）
    var startX = 0;
    var startY = 0;

    document.addEventListener('touchstart', function(e) {
        if (nav && !nav.classList.contains('open')) {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        }
    }, { passive: true });

    document.addEventListener('touchmove', function(e) {
        if (nav && !nav.classList.contains('open')) {
            var currentX = e.touches[0].clientX;
            var currentY = e.touches[0].clientY;
            var deltaX = currentX - startX;
            var deltaY = currentY - startY;

            // 如果是向右滑动（deltaX > 0）且水平滑动大于垂直滑动
            if (deltaX > 10 && Math.abs(deltaX) > Math.abs(deltaY)) {
                e.preventDefault();
            }
        }
    }, { passive: false });

    // Article preview
    var previewBtn = document.getElementById('previewBtn');
    var editor = document.getElementById('editor');
    var preview = document.getElementById('preview');
    if (previewBtn && editor && preview) {
        previewBtn.addEventListener('click', function() {
            preview.innerHTML = editor.value;
            preview.style.display = 'block';
        });
    }

    // Delete confirmation
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (!confirm(this.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

});