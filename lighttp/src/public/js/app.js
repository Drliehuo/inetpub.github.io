/* Lighttp v1.0.2 - Global JavaScript */
"use strict";

document.addEventListener('DOMContentLoaded', function() {

    // Mobile menu toggle
    var toggle = document.getElementById('menuToggle');
    var nav = document.getElementById('navLinks');

    if (toggle && nav) {
        toggle.addEventListener('click', function() {
            toggle.classList.toggle('active');
            nav.classList.toggle('open');
        });

        // Close menu on link click (mobile)
        nav.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                toggle.classList.remove('active');
                nav.classList.remove('open');
            });
        });
    }

    // Close mobile menu on outside click
    document.addEventListener('click', function(e) {
        if (nav && nav.classList.contains('open')) {
            var isClickInside = nav.contains(e.target) || toggle.contains(e.target);
            if (!isClickInside) {
                toggle.classList.remove('active');
                nav.classList.remove('open');
            }
        }
    });

    // Article content preview (admin)
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
