<?php
if(defined('APP_LOADING_SCREEN_LOADED')){
    return;
}

define('APP_LOADING_SCREEN_LOADED', true);
?>

<div id="page-loader" class="page-loader" aria-live="polite" aria-label="Memuat halaman">
    <div class="loader-card">
        <div class="loader-ring">
            <span></span>
        </div>

        <div class="loader-text">
            <strong>Memuat Data</strong>
            <small>Mohon tunggu sebentar...</small>
        </div>

        <div class="loader-time" id="loader-time">
            <span>Waktu proses</span>
            <strong id="loader-elapsed">00:00</strong>
        </div>

        <div class="loader-steps" id="loader-steps">
            <div class="loader-step active">Membaca data nilai</div>
            <div class="loader-step">Menghitung centroid</div>
            <div class="loader-step">Menyimpan hasil</div>
        </div>
    </div>
</div>

<div id="app-toast" class="app-toast" role="status" aria-live="polite">
    <div class="toast-icon">
        <i class="bi bi-check-lg"></i>
    </div>
    <div>
        <strong id="toast-title">Berhasil</strong>
        <small id="toast-message">Aksi berhasil dilakukan.</small>
    </div>
</div>

<style>
body:not(.app-loaded) .content,
body:not(.app-loaded) .wrapper,
body:not(.app-loaded) .container-custom{
    opacity:0;
}

.content,
.wrapper,
.container-custom{
    transition:opacity 0.35s ease;
}

body.app-loaded .content,
body.app-loaded .wrapper,
body.app-loaded .container-custom{
    animation:page-enter 0.45s ease both;
}

.animate-in{
    opacity:0;
    transform:translateY(14px);
}

.animate-in.revealed{
    animation:card-enter 0.48s ease both;
}

.table tbody tr{
    transition:transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.table tbody tr:hover{
    transform:translateX(3px);
}

.table-responsive{
    border:1px solid #e2e8f0;
    border-radius:16px;
    box-shadow:0 4px 14px rgba(15,23,42,0.05);
    overflow-x:auto;
    overflow-y:hidden;
    position:relative;
    scrollbar-color:#94a3b8 #f1f5f9;
    scrollbar-width:thin;
}

.table-responsive > .table{
    margin-bottom:0;
}

.table-responsive::-webkit-scrollbar{
    height:10px;
}

.table-responsive::-webkit-scrollbar-track{
    background:#f1f5f9;
    border-radius:999px;
}

.table-responsive::-webkit-scrollbar-thumb{
    background:#94a3b8;
    border:2px solid #f1f5f9;
    border-radius:999px;
}

.table-responsive::-webkit-scrollbar-thumb:hover{
    background:#64748b;
}

body:not(.app-loaded) .table-responsive::after{
    content:'';
    position:absolute;
    inset:52px 0 0;
    min-height:150px;
    border-radius:0 0 16px 16px;
    background:
        linear-gradient(90deg,transparent,rgba(255,255,255,0.8),transparent),
        repeating-linear-gradient(
            180deg,
            #eef2f7 0,
            #eef2f7 18px,
            transparent 18px,
            transparent 52px
        );
    background-size:220px 100%,100% 100%;
    animation:skeleton-shimmer 1.2s linear infinite;
    pointer-events:none;
}

.empty-animate i,
.card-box.text-center > i{
    animation:soft-float 2.4s ease-in-out infinite;
}

.empty-animate{
    animation:empty-pulse 2.3s ease-in-out infinite;
}

.btn,
.sidebar a{
    will-change:transform;
}

.btn.is-loading{
    position:relative;
    pointer-events:none;
    opacity:0.85;
}

.btn.is-loading::after,
button.is-loading::after{
    content:'';
    width:16px;
    height:16px;
    margin-left:10px;
    border-radius:50%;
    border:2px solid currentColor;
    border-top-color:transparent;
    display:inline-block;
    vertical-align:-3px;
    animation:loader-spin 0.75s linear infinite;
}

.page-loader{
    position:fixed;
    inset:0;
    background:rgba(244,247,251,0.92);
    backdrop-filter:blur(10px);
    z-index:99999;
    display:flex;
    align-items:center;
    justify-content:center;
    opacity:1;
    visibility:visible;
    transition:opacity 0.35s ease, visibility 0.35s ease;
}

.page-loader.loader-hidden{
    opacity:0;
    visibility:hidden;
    pointer-events:none;
}

.loader-card{
    min-width:260px;
    padding:28px;
    border-radius:22px;
    background:#ffffff;
    box-shadow:0 18px 50px rgba(15,23,42,0.14);
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:16px;
    text-align:center;
}

.loader-ring{
    width:68px;
    height:68px;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    background:conic-gradient(from 0deg,#2563eb,#93c5fd,#2563eb);
    animation:loader-spin 0.9s linear infinite;
}

.loader-ring span{
    width:50px;
    height:50px;
    border-radius:50%;
    background:#ffffff;
    display:block;
}

.loader-text strong{
    display:block;
    color:#111827;
    font-size:17px;
    margin-bottom:4px;
}

.loader-text small{
    color:#6b7280;
    font-size:13px;
}

.loader-time{
    display:none;
    align-items:center;
    justify-content:center;
    gap:10px;
    width:100%;
    padding:10px 12px;
    border-radius:14px;
    background:#eff6ff;
    color:#1d4ed8;
}

.loader-time.is-active{
    display:flex;
}

.loader-time span{
    font-size:12px;
    font-weight:600;
}

.loader-time strong{
    font-size:18px;
    line-height:1;
    font-variant-numeric:tabular-nums;
}

.loader-steps{
    display:none;
    width:100%;
    margin-top:4px;
    text-align:left;
}

.loader-steps.is-active{
    display:grid;
    gap:8px;
}

.loader-step{
    position:relative;
    padding-left:22px;
    color:#6b7280;
    font-size:13px;
}

.loader-step::before{
    content:'';
    position:absolute;
    left:0;
    top:6px;
    width:9px;
    height:9px;
    border-radius:50%;
    background:#d1d5db;
}

.loader-step.active{
    color:#1d4ed8;
    font-weight:700;
}

.loader-step.active::before{
    background:#2563eb;
    box-shadow:0 0 0 6px rgba(37,99,235,0.12);
}

.app-toast{
    position:fixed;
    right:24px;
    top:24px;
    z-index:100000;
    display:flex;
    align-items:center;
    gap:12px;
    width:min(360px,calc(100vw - 32px));
    padding:16px;
    border-radius:18px;
    background:#ffffff;
    box-shadow:0 18px 50px rgba(15,23,42,0.16);
    transform:translateX(120%);
    opacity:0;
    transition:transform 0.35s ease, opacity 0.35s ease;
}

.app-toast.show{
    transform:translateX(0);
    opacity:1;
}

.toast-icon{
    width:42px;
    height:42px;
    border-radius:14px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#dcfce7;
    color:#16a34a;
    font-size:22px;
    flex:none;
}

.app-toast strong,
.app-toast small{
    display:block;
}

.app-toast strong{
    color:#111827;
    margin-bottom:2px;
}

.app-toast small{
    color:#6b7280;
}

@keyframes loader-spin{
    to{
        transform:rotate(360deg);
    }
}

@keyframes page-enter{
    from{
        opacity:0;
        transform:translateY(10px);
    }

    to{
        opacity:1;
        transform:translateY(0);
    }
}

@keyframes card-enter{
    from{
        opacity:0;
        transform:translateY(14px);
    }

    to{
        opacity:1;
        transform:translateY(0);
    }
}

@keyframes skeleton-shimmer{
    to{
        background-position:220px 0,0 0;
    }
}

@keyframes soft-float{
    0%,
    100%{
        transform:translateY(0);
    }

    50%{
        transform:translateY(-8px);
    }
}

@keyframes empty-pulse{
    0%,
    100%{
        opacity:1;
    }

    50%{
        opacity:0.68;
    }
}

@media (prefers-reduced-motion:reduce){
    *,
    *::before,
    *::after{
        animation-duration:0.01ms !important;
        animation-iteration-count:1 !important;
        scroll-behavior:auto !important;
        transition-duration:0.01ms !important;
    }
}
</style>

<script>
(function(){
    const loader = document.getElementById('page-loader');
    const loaderSteps = document.getElementById('loader-steps');
    const loaderText = loader ? loader.querySelector('.loader-text strong') : null;
    const loaderSubtext = loader ? loader.querySelector('.loader-text small') : null;
    const loaderTime = document.getElementById('loader-time');
    const loaderElapsed = document.getElementById('loader-elapsed');
    let progressTimer = null;
    let elapsedTimer = null;
    let startTime = 0;

    if(!loader){
        return;
    }

    const formatElapsed = function(totalSeconds){
        const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
        const seconds = String(totalSeconds % 60).padStart(2, '0');

        return minutes + ':' + seconds;
    };

    const resetElapsed = function(){
        if(elapsedTimer){
            clearInterval(elapsedTimer);
            elapsedTimer = null;
        }

        if(loaderTime){
            loaderTime.classList.remove('is-active');
        }

        if(loaderElapsed){
            loaderElapsed.textContent = '00:00';
        }
    };

    const startElapsed = function(){
        if(!loaderTime || !loaderElapsed){
            return;
        }

        resetElapsed();
        startTime = Date.now();
        loaderTime.classList.add('is-active');
        loaderElapsed.textContent = '00:00';

        elapsedTimer = setInterval(function(){
            const totalSeconds = Math.floor((Date.now() - startTime) / 1000);
            loaderElapsed.textContent = formatElapsed(totalSeconds);
        }, 1000);
    };

    const resetProgress = function(){
        if(progressTimer){
            clearInterval(progressTimer);
            progressTimer = null;
        }

        resetElapsed();

        if(loaderText){
            loaderText.textContent = 'Memuat Data';
        }

        if(loaderSubtext){
            loaderSubtext.textContent = 'Mohon tunggu sebentar...';
        }

        if(!loaderSteps){
            return;
        }

        loaderSteps.classList.remove('is-active');

        loaderSteps.querySelectorAll('.loader-step').forEach(function(step, index){
            step.classList.toggle('active', index === 0);
        });
    };

    const startProgress = function(){
        if(!loaderSteps){
            return;
        }

        const steps = loaderSteps.querySelectorAll('.loader-step');
        let activeStep = 0;

        loaderSteps.classList.add('is-active');

        progressTimer = setInterval(function(){
            steps[activeStep].classList.remove('active');
            activeStep = Math.min(activeStep + 1, steps.length - 1);
            steps[activeStep].classList.add('active');

            if(activeStep === steps.length - 1){
                clearInterval(progressTimer);
                progressTimer = null;
            }
        }, 900);
    };

    const showLoader = function(withProgress){
        if(withProgress){
            if(loaderText){
                loaderText.textContent = 'Memproses Clustering';
            }

            if(loaderSubtext){
                loaderSubtext.textContent = 'Mohon tunggu hingga perhitungan selesai...';
            }

            startElapsed();
            startProgress();
        }

        loader.classList.remove('loader-hidden');
    };

    const hideLoader = function(){
        loader.classList.add('loader-hidden');
        document.body.classList.add('app-loaded');
        resetProgress();
    };

    const showToast = function(title, message){
        const toast = document.getElementById('app-toast');
        const toastTitle = document.getElementById('toast-title');
        const toastMessage = document.getElementById('toast-message');

        if(!toast || !toastTitle || !toastMessage){
            return;
        }

        toastTitle.textContent = title || 'Berhasil';
        toastMessage.textContent = message || 'Aksi berhasil dilakukan.';
        toast.classList.add('show');

        setTimeout(function(){
            toast.classList.remove('show');
        }, 3200);
    };

    const readToastFromUrl = function(){
        const params = new URLSearchParams(window.location.search);

        if(!params.has('toast')){
            return;
        }

        const message = params.get('toast');

        showToast('Berhasil', message);
        params.delete('toast');

        const cleanUrl =
        window.location.pathname +
        (params.toString() ? '?' + params.toString() : '') +
        window.location.hash;

        window.history.replaceState({}, document.title, cleanUrl);
    };

    const animateCounters = function(){
        const counters = document.querySelectorAll(
            '.card-box h1, .card-box h3, .card-box h5.text-success, .card-box h5.text-primary, .summary-card h5, .rank-score'
        );

        counters.forEach(function(counter){
            const raw = counter.textContent.trim().replace(',', '.');

            if(!/^\d+(\.\d+)?$/.test(raw)){
                return;
            }

            const target = Number(raw);
            const decimals = raw.includes('.') ? 2 : 0;
            const duration = 900;
            const start = performance.now();

            const tick = function(now){
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                const value = target * eased;

                counter.textContent = decimals
                ? value.toFixed(decimals)
                : Math.round(value);

                if(progress < 1){
                    requestAnimationFrame(tick);
                }else{
                    counter.textContent = decimals
                    ? target.toFixed(decimals)
                    : String(target);
                }
            };

            requestAnimationFrame(tick);
        });
    };

    const prepareAnimations = function(){
        const targets = document.querySelectorAll(
            '.card-box, .form-card, .import-card, .summary-card, .guide-card, .step-card, .ranking-item'
        );

        targets.forEach(function(target, index){
            target.classList.add('animate-in');
            target.style.animationDelay = Math.min(index * 45, 360) + 'ms';
        });

        if('IntersectionObserver' in window){
            const observer = new IntersectionObserver(function(entries){
                entries.forEach(function(entry){
                    if(entry.isIntersecting){
                        entry.target.classList.add('revealed');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold:0.12
            });

            targets.forEach(function(target){
                observer.observe(target);
            });
        }else{
            targets.forEach(function(target){
                target.classList.add('revealed');
            });
        }
    };

    window.addEventListener('load', function(){
        prepareAnimations();
        animateCounters();
        readToastFromUrl();
        setTimeout(hideLoader, 300);
    });

    window.addEventListener('pageshow', function(event){
        if(event.persisted){
            hideLoader();
        }
    });

    document.addEventListener('submit', function(event){
        if(event.target.dataset.skipLoader === 'true'){
            return;
        }

        const filterFieldName =
        event.target.dataset.filterRequired;

        if(filterFieldName){
            const filterField = event.target.elements[filterFieldName];

            if(!filterField || filterField.value.trim() === ''){
                event.preventDefault();

                alert(
                    event.target.dataset.filterMessage ||
                    'Silakan pilih filter terlebih dahulu.'
                );

                if(filterField){
                    filterField.focus();
                }

                return;
            }
        }

        if(!event.defaultPrevented){
            const submitButton = event.target.querySelector(
                'button[type="submit"], input[type="submit"]'
            );

            if(submitButton){
                submitButton.classList.add('is-loading');
            }

            showLoader(event.target.dataset.progress === 'clustering');
        }
    });

    document.addEventListener('click', function(event){
        const link = event.target.closest('a');

        if(!link || event.defaultPrevented){
            return;
        }

        const href = link.getAttribute('href');

        if(
            !href ||
            href.startsWith('#') ||
            href.startsWith('javascript:') ||
            link.target === '_blank' ||
            link.hasAttribute('download')
        ){
            return;
        }

        showLoader();
    });
})();
</script>
