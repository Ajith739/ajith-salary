/**
 * Micro-Animations & Counter Engine
 * Powered by GSAP and requestAnimationFrame
 */

(function() {
    'use strict';

    // Format number in Indian Rupee format
    function formatINR(val) {
        val = Math.round(val);
        const neg = val < 0;
        val = Math.abs(val);
        const s = val.toString();
        const last3 = s.substring(s.length - 3);
        const other = s.substring(0, s.length - 3);
        const formatted = other !== '' ? other.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + last3 : last3;
        return (neg ? '-' : '') + '₹' + formatted;
    }

    // Number counting animation
    function animateCounters() {
        const elements = document.querySelectorAll('[data-count]');
        elements.forEach(el => {
            const target = parseFloat(el.getAttribute('data-count')) || 0;
            const duration = 1200; // ms
            const startTime = performance.now();

            function update(now) {
                const elapsed = now - startTime;
                const progress = Math.min(elapsed / duration, 1);
                // Ease out quart
                const easeProgress = 1 - Math.pow(1 - progress, 4);
                const current = target * easeProgress;

                el.textContent = formatINR(current);

                if (progress < 1) {
                    requestAnimationFrame(update);
                } else {
                    el.textContent = formatINR(target);
                }
            }

            requestAnimationFrame(update);
        });
    }

    // GSAP page entrance animation
    function animateEntrances() {
        if (typeof gsap === 'undefined') return;

        const animateElements = document.querySelectorAll('[data-animate]');
        if (animateElements.length > 0) {
            gsap.from(animateElements, {
                y: 25,
                opacity: 0,
                duration: 0.65,
                stagger: 0.08,
                ease: 'power2.out'
            });
        }
    }

    // Animate radial health score gauge
    function animateGauge() {
        const gaugeCircle = document.querySelector('.gauge-circle[data-score]');
        if (!gaugeCircle) return;

        const score = parseFloat(gaugeCircle.getAttribute('data-score')) || 0;
        const circle = gaugeCircle.querySelector('svg circle:last-child');
        if (!circle) return;

        const radius = 52;
        const circumference = 2 * Math.PI * radius;
        const targetOffset = circumference * (1 - score / 100);

        // Animate stroke dashoffset
        circle.style.strokeDashoffset = circumference;
        circle.style.transition = 'stroke-dashoffset 1.4s cubic-bezier(0.4, 0, 0.2, 1)';
        
        setTimeout(() => {
            circle.style.strokeDashoffset = targetOffset;
        }, 150);
    }

    document.addEventListener('DOMContentLoaded', () => {
        animateEntrances();
        animateCounters();
        animateGauge();
    });
})();
