<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <title></title>
  <script data-cfasync="false" src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
  <script data-cfasync="false" src="https://cdn.jsdelivr.net/npm/hls.js@1"></script>
  <script data-cfasync="false" src="https://cdnjs.cloudflare.com/ajax/libs/shaka-player/4.7.9/shaka-player.compiled.js"></script>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;-webkit-touch-callout:none;-webkit-user-select:none;user-select:none}
    :root {
      --accent: #6366f1;     
      --bg: #000;
      --surface: #0a0a0a;    
      --border: rgba(255,255,255,.1);
      --text: #e8e8f0;
      --muted: #7070a0;
    }
    html,body{height:100%;width:100%;overflow:hidden;background:var(--bg);font-family:'DM Sans',sans-serif;color:var(--text)}
    #shell{position:fixed;inset:0;display:flex;flex-direction:column;background:var(--bg);overflow:hidden}
    #main-row{flex:1;min-height:0;display:flex;flex-direction:row;overflow:hidden}
    #video-col{flex:1;min-width:0;min-height:0;position:relative;background:#000;overflow:hidden}
    #vid-box{position:absolute;inset:0;background:#000;display:flex;align-items:center;justify-content:center}
    video{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;background:#000;display:block;outline:none}
    .ov{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;z-index:10}
    #ovLoad{background:rgba(0,0,0,.85)}
    #ovLoad.off{display:none}
    
    .spinner {
      width: 44px;
      height: 44px;
      border: 4.5px solid rgba(99,102,241,.18);
      border-top-color: var(--accent);
      border-radius: 50%;
      animation: spin .8s linear infinite;
    }
    @keyframes spin{to{transform:rotate(360deg)}}
    #ovLoad p{font-size:13px;color:rgba(255,255,255,.65)}
    #ovErr{background:rgba(0,0,0,.88);display:none;padding:24px;text-align:center;gap:12px}
    #ovErr.on{display:flex}
    #ovErr p{font-size:14px;color:#ff6584;max-width:320px;line-height:1.5}
    #ovErr button{padding:9px 24px;background:var(--accent);color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer;font-family:inherit;transition:opacity .2s}
    #ovErr button:hover{opacity:.85}
    
    #bufSpin{position:absolute;inset:0;display:none;align-items:center;justify-content:center;z-index:8;pointer-events:none}
    #bufSpin.on{display:flex}
    #bufSpin::after{content:'';width:44px;height:44px;border-radius:50%;border:4.5px solid rgba(99,102,241,.18);border-top-color:var(--accent);animation:spin .75s linear infinite}

    #ctrl-overlay {
      position:absolute;
      inset:0;
      z-index:20;
      display:flex;
      flex-direction:column;
      opacity: 0;
      transition: opacity .35s ease;
      pointer-events: none;
    }
    /* ctrl-overlay is always pointer-events:none; individual children (header/footer/buttons)
       have their own pointer-events:auto so they stay clickable. Keeping the overlay itself
       transparent to touches lets tap-shield handle double-tap seek on ALL mobile states. */
    #ctrl-overlay.show-controls {opacity: 1; pointer-events: none;}
    
    /* Shield and Pointer Fixes */
    #tap-shield {position:absolute; inset:0; z-index:18; background:rgba(0,0,0,0.001); display:block; pointer-events: auto !important;}
    #video-col.hide-cursor,
    #video-col.hide-cursor * {
        cursor: none !important;
    }
    
    body.player-locked #tap-shield,
    body.player-locked #ctrl-mid,
    body.player-locked #vid-box,
    body.player-locked video {
        cursor: default;
    }

    #player-header{display:flex;align-items:center;gap:8px;padding:10px 14px;background:linear-gradient(rgba(0,0,0,.72) 0%,transparent 100%);flex-shrink:0;pointer-events:auto;z-index:25;transition:opacity .2s ease}
    .hdr-back{background:none;border:none;color:#fff;cursor:pointer;padding:6px;display:flex;align-items:center;border-radius:8px;flex-shrink:0;transition:transform .12s}
    .hdr-back:active{transform:scale(.88)}
    .hdr-back svg{width:22px;height:22px;stroke:#fff;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
    #player-title{flex:1;min-width:0;font-size:14px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    
    #ctrl-mid{flex:1;pointer-events:none;cursor:pointer;background:transparent;z-index:22;display:flex;align-items:center;justify-content:center}
    .mobile-center-play { display: none; position: absolute; inset: 0; margin: auto; background: transparent; border: none; align-items: center; justify-content: center; pointer-events: auto; color: #fff; transition: transform 0.1s; width: 64px; height: 64px; z-index: 24; outline: none; }
    .mobile-center-play:active { transform: scale(0.9); }
    #video-col.is-buffering .mobile-center-play { display: none !important; }
    body.is-touch .mobile-center-play { display: flex; }
    .mobile-center-play svg { width: 56px; height: 56px; fill: white; filter: drop-shadow(0 2px 10px rgba(0,0,0,0.6)); }
    
    #player-footer{flex-shrink:0;background:linear-gradient(transparent 0%,rgba(0,0,0,.82) 100%);padding:6px 0 10px;pointer-events:auto;z-index:25;transition:opacity .2s ease}
    .prog-wrap{padding:0 0 6px}
    .time-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:6px; padding:0 14px }
    .t-left{display:flex;align-items:center;gap:6px}
    .t-time{color:#fff;font-size:13px;font-family:monospace;font-weight:700}
    
    .spd-badge { background: #6366f1 !important; color: #ffffff !important; font-size: 10px; font-weight: 800; padding: 2.5px 6px; border-radius: 4px; font-family: sans-serif; }
    .live-badge{display:none;align-items:center;gap:5px;font-size:11px;font-weight:700;color:#fff;padding:2px 6px;border-radius:4px}
    .live-badge.on{display:flex}
    .live-dot{width:7px;height:7px;border-radius:50%;background:red;animation:blink 1.4s ease infinite}
    @keyframes blink{50%{opacity:0}}
    .live-badge.behind-live{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.12);cursor:pointer;transition:background .2s,transform .1s}
    .live-badge.behind-live:hover{background:rgba(255,255,255,.25)}
    .live-badge.behind-live:active{transform:scale(.95)}
    .live-badge.behind-live .live-dot{background:#aaa;animation:none}
    
    .bar-wrap{position:relative;height:24px;display:flex;align-items:center;touch-action:none}
    .bar-bg{position:absolute;left:0;right:0;height:6px;background:rgba(255, 255, 255, 0.16);border-radius:999px;overflow:hidden}
    .bar-buf{height:100%;background:#b9b9b9;position:absolute;left:0;border-radius:999px}
    .bar-fill{height:100%;background:linear-gradient(90deg,#BDB4FE 0%,#7A5AF8 100%);position:absolute;left:0;border-radius:999px;transition: width 0.08s cubic-bezier(0.1, 0.8, 0.2, 1);}
    .bar-thumb{position:absolute;top:50%;transform:translate(-50%,-50%) scale(0);opacity:0;width:14px;height:14px;background:#fff;border-radius:50%;pointer-events:none;box-shadow:0 0 8px rgba(255,255,255,.45);transition:left 0.08s cubic-bezier(0.1, 0.8, 0.2, 1), transform .18s ease, opacity .18s ease;}
    .bar-wrap:hover .bar-thumb{transform:translate(-50%,-50%) scale(1);opacity:1}
    .bar-input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;z-index:5}

    .cbtn svg[stroke] { stroke-width: 2.5 !important; }
    #settBtn svg path { stroke-width: 2.2 !important; }
    #slidesPanelBtn svg rect { stroke-width: 2.4 !important; }
    .sopt { font-weight: 600 !important; }
    
    .btn-row{display:flex;align-items:center;justify-content:space-between;padding:0 6px}
    .btn-l,.btn-r{display:flex;align-items:center}
    
    .cbtn { background: none; border: none; color: #fff; cursor: pointer; min-width: 44px; min-height: 44px; padding: 4px; display: flex; align-items: center; justify-content: center; border-radius: 4px; flex-shrink: 0; outline: none; transition: all .2s; }
    .cbtn:active { transform: scale(.9); opacity: .7; }

    #fwBtn { background-image: url('https://www.pw.live/watch/forward.svg'); background-repeat: no-repeat; background-position: center; height: 2rem; width: 2rem; margin-left: .75rem; margin-right: 0rem; }
    #rwBtn { background-image: url('https://www.pw.live/watch/backward.svg'); background-repeat: no-repeat; background-position: center; height: 2rem; width: 2rem; margin-left: .75rem; margin-right: 0rem; }

    @media (max-width: 580px) {
      #shell.layout-mobile #slidesPanelBtn {
        display: none !important;
      }
    }
    @media (max-width: 480px) {
      .cbtn {
        min-width: 38px !important;
        min-height: 38px !important;
        padding: 2px !important;
      }
      #fwBtn, #rwBtn {
        margin-left: 0.4rem !important;
        margin-right: 0rem !important;
      }
      .btn-row {
        padding: 0 4px !important;
      }
      .prog-wrap {
        padding: 0 0 4px !important;
      }
      .time-row {
        padding: 0 10px !important;
      }
    }

    @keyframes sk-l { 0% { transform: rotate(0); } 50% { transform: rotate(-35deg); } 100% { transform: rotate(0); } }
    @keyframes sk-r { 0% { transform: rotate(0); } 50% { transform: rotate(35deg); } 100% { transform: rotate(0); } }
    .anim-l { animation: sk-l .32s ease; }
    .anim-r { animation: sk-r .32s ease; }

    .vol-grp{display:flex;align-items:center}
    .vol-range{-webkit-appearance:none;width:0;height:4px;border-radius:2px;background:rgba(255,255,255,.25);outline:none;cursor:pointer;opacity:0;transition:width .3s,opacity .3s;margin-left:0}
    .vol-grp:hover .vol-range,.vol-grp:focus-within .vol-range{width:52px;opacity:1;margin-left:4px}
    .vol-range::-webkit-slider-thumb{-webkit-appearance:none;width:13px;height:13px;border-radius:50%;background:#fff}
    .vol-range::-moz-range-thumb{width:13px;height:13px;border-radius:50%;background:#fff;border:none}

    #lock-overlay-shield {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      z-index: 45;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 1;
      visibility: visible;
      pointer-events: auto;
      transition: opacity 0.35s ease, visibility 0.35s ease;
    }
    #lock-overlay-shield.hidden-fade { opacity: 0; visibility: hidden; pointer-events: none; }
    body.player-locked #ctrl-overlay { pointer-events: none !important; opacity: 0 !important; }

    /* YouTube Exclusive Styling */
    body.is-youtube #tap-shield,
    body.is-youtube #lock-overlay-shield,
    body.is-youtube #ctrl-mid,
    body.is-youtube #player-footer,
    body.is-youtube .ov,
    body.is-youtube #bufSpin {
        display: none !important;
    }
    body.is-youtube #vid-box { top: 46px; }
    body.is-youtube #ctrl-overlay { opacity: 1 !important; pointer-events: none !important; }
    body.is-youtube #player-header { pointer-events: auto !important; background: #000; opacity: 1 !important; height: 46px; }

    .header-menu-container { position: relative; display: inline-block; }
    .dropdown-menu-list {
      display: none; position: absolute; right: 0; top: 48px;
      background: #0a0a0a; min-width: 190px; border-radius: 6px;
      border: 1px solid rgba(255,255,255,.08); box-shadow: 0 8px 32px rgba(0,0,0,.9);
      z-index: 100; overflow: hidden; padding: 0;
    }
    .dropdown-menu-list.show { display: block; }
    .dropdown-item-btn {
      width: 100%; border: none; background: transparent; color: #fff;
      display: flex; align-items: center; gap: 12px; padding: 16px 18px;
      font-size: 14px; font-weight: 600; cursor: pointer; text-align: left;
      border-radius: 0; border-bottom: 1px solid rgba(255,255,255,.04);
      transition: background .12s; letter-spacing: .3px;
    }
    .dropdown-item-btn:last-child { border-bottom: none; }
    .dropdown-item-btn:hover { background: rgba(99,102,241,.12); }
    .dropdown-item-btn:active { background: rgba(99,102,241,.18); }

    #side-panel{width:0;overflow:hidden;flex-shrink:0;background:var(--surface);border-left:1px solid var(--border);display:none;flex-direction:column;transition:width .3s cubic-bezier(.4,0,.2,1);z-index: 40;}
    #side-panel.open { width: 340px !important; }
    #bottom-panel{flex-shrink:0;background:var(--surface);border-top:1px solid var(--border);display:none;flex-direction:column;height:0;overflow:hidden;transition:height .35s cubic-bezier(.4,0,.2,1);z-index: 40;}
    #bottom-panel.open{height:60vh;max-height:calc(100dvh - 120px);}
    
    #shell.layout-desktop #side-panel{display:flex}
    #shell.layout-desktop #bottom-panel{display:none!important}
    #shell.layout-mobile #bottom-panel{display:flex}
    #shell.layout-mobile #side-panel{display:none!important}
    
    .panel-inner{display:flex;flex-direction:column;height:100%;min-height:0;background:#000000 !important;}
    .panel-hdr{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid var(--border);flex-shrink:0;background:#13131a}
    .panel-hdr-title{font-size:15px;font-weight:700;color:var(--text)}
    .panel-close{background:none;border:none;cursor:pointer;color:var(--muted);padding:4px;display:flex;align-items:center;border-radius:6px}
    .panel-close svg{width:20px;height:20px;stroke:currentColor;fill:none;stroke-width:2.5}
    
    .tab-header-row { display: flex; border-bottom: 1px solid var(--border); background: #13131a; flex-shrink: 0; }
    .tab-click-nav { flex: 1; padding: 12px; background: transparent; border: none; color: var(--muted); font-size: 13px; font-weight: 600; cursor: pointer; text-align: center; border-bottom: 2px solid transparent; transition: all .2s; }
    .tab-click-nav.active { color: var(--accent); border-bottom-color: var(--accent); background: rgba(99,102,241,0.05); }

    .panel-body { flex:1; overflow-y:auto; -webkit-overflow-scrolling:touch; overscroll-behavior:contain; padding:12px; position:relative; background:#000000 !important; }
    .panel-loading-box { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; min-height: 200px; width: 100%; }
    
    .tl-list{display:flex;flex-direction:column;gap:12px}
    .tl-card{position:relative;border-radius:12px;overflow:hidden;cursor:pointer;border:2px solid transparent;transition:all .15s}
    
    .type-slides-container .tl-card { width: 100%; aspect-ratio: 16/9; background: #000; }
    .type-slides-container .tl-card:hover { border-color: rgba(99,102,241,.5); }
    .type-slides-container .tl-card.cur { border-color: var(--accent); }
    
    .type-playlist-container .tl-card { display: flex; flex-direction: row; gap: 14px; padding: 10px; background: #13131a; align-items: center; }
    .type-playlist-container .tl-card:hover { border-color: rgba(99,102,241,.4); background: #1a1a24; }
    .type-playlist-container .tl-card.cur { border-color: var(--accent); background: #1c1a36; }

    .tl-img-wrapper{position:relative;width:115px;aspect-ratio:16/9;border-radius:8px;overflow:hidden;background:#050508;flex-shrink:0}
    .type-slides-container .tl-img-wrapper { width: 100%; height: 100%; border-radius: 0; }

    .tl-img{width:100%;height:100%;object-fit:cover;display:block;opacity:0;transition:opacity 0.3s ease}
    .tl-img.loaded{opacity:1}
    .tl-duration-badge{position:absolute;bottom:4px;right:4px;background:rgba(0,0,0,0.75);color:#fff;font-family:monospace;font-size:10px;padding:2px 4px;border-radius:4px;font-weight:600;z-index:3}
    .tl-slide-number-tag { position: absolute; bottom: 8px; left: 8px; background: #5b46e8; color: #fff; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 6px; z-index: 3; box-shadow: 0 2px 8px rgba(0,0,0,0.4); }

    .tl-details{flex:1;min-width:0;display:flex;flex-direction:column;gap:4px}
    .tl-name{font-size:13px;font-weight:600;color:var(--text);line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;text-overflow:ellipsis}
    
    .type-slides-container .tl-details { position: absolute; inset: 0; background: linear-gradient(transparent 40%, rgba(0,0,0,0.85) 100%); padding: 10px; justify-content: flex-end; z-index: 2; }
    .type-slides-container .tl-name { display: none; }

    .tl-cur-badge{background:var(--accent);color:#fff;font-size:9px;font-weight:700;padding:2px 6px;border-radius:4px;align-self:flex-start;display:none}
    .tl-card.cur .tl-cur-badge{display:inline-block}
    .type-slides-container .tl-cur-badge { position: absolute; top: 8px; left: 8px; display: none; z-index: 4; }
    .type-slides-container .tl-card.cur .tl-cur-badge { display: block; }

    .ss-content-card { border-radius: 10px !important; border: none !important; transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease !important; background: #13131a; position: relative; }
    html.dark .ss-content-card:hover { transform: translateY(-3px); box-shadow: 0 0 25px rgba(0, 0, 0, 0.85) !important; }

    .pdf-btn-wrap { position: absolute !important; bottom: 12px !important; left: 12px !important; right: 12px !important; background: rgba(99, 102, 241, 0.12) !important; border: none !important; padding: 6px 12px !important; border-radius: 8px !important; display: flex !important; align-items: center !important; justify-content: space-between !important; box-sizing: border-box !important; }
    .pdf-circle-btn { width: 34px !important; height: 34px !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; transition: all 0.25s ease !important; border: none !important; outline: none !important; cursor: pointer !important; padding: 0 !important; box-sizing: border-box !important; }
    html.dark .pdf-circle-btn { background: #111317 !important; border: none !important; box-shadow: 2px 4px 12px rgba(0, 0, 0, 0.45) !important; }
    .pdf-circle-btn:hover:not(:disabled) { background: #6366f1 !important; box-shadow: 2px 6px 14px rgba(99, 102, 241, 0.35) !important; }
    .pdf-circle-btn .pdf-icon { width: 16px !important; height: 16px !important; fill: #6366f1 !important; color: #6366f1 !important; transition: fill 0.25s ease, color 0.25s ease !important; flex-shrink: 0 !important; display: inline-block !important; vertical-align: middle !important; overflow: visible !important; }
    .pdf-circle-btn:hover:not(:disabled) .pdf-icon { fill: #ffffff !important; color: #ffffff !important; }

    .ss-dpp-quiz-card { padding: 12px 14px !important; display: flex !important; flex-direction: column !important; justify-content: space-between !important; box-sizing: border-box !important; min-height: 145px !important; }
    .ss-dpp-quiz-card-info { display: flex !important; flex-direction: column !important; justify-content: center !important; min-width: 0 !important; }
    .ss-dpp-meta-row { font-size: 12px !important; font-weight: 700 !important; color: #ff9800 !important; margin-bottom: 4px !important; text-transform: uppercase !important; letter-spacing: 0.5px !important; }
    html.dark .ss-dpp-meta-row { color: #ffa726 !important; }
    .ss-dpp-title { font-size: 14.5px !important; font-weight: 600 !important; color: #111827 !important; line-height: 1.35 !important; margin-bottom: 6px !important; display: -webkit-box !important; -webkit-line-clamp: 2 !important; -webkit-box-orient: vertical !important; overflow: hidden !important; text-overflow: ellipsis !important; }
    html.dark .ss-dpp-title { color: #f9fafb !important; }
    .ss-dpp-stats-row { display: flex !important; align-items: center !important; flex-wrap: wrap !important; gap: 6px !important; font-size: 12px !important; color: #4b5563 !important; font-weight: 700 !important; }
    html.dark .ss-dpp-stats-row { color: #9ca3af !important; }
    
    .ss-dpp-btn { width: 100% !important; background: #f3f4f6 !important; color: #111827 !important; font-weight: 700 !important; padding: 10px 18px !important; border-radius: 10px !important; display: flex !important; align-items: center !important; justify-content: center !important; gap: 8px !important; border: none !important; font-size: 13.5px !important; cursor: pointer !important; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important; box-sizing: border-box !important; }
    html.dark .ss-dpp-btn { background: #232338 !important; color: #f9fafb !important; }
    html.dark .ss-dpp-btn:hover { background: #6366f1 !important; color: #ffffff !important; }
    .ss-dpp-btn-arrow { width: 20px !important; height: 20px !important; background: #111827 !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; color: #fff !important; transition: all 0.25s ease !important; box-sizing: border-box !important; }
    html.dark .ss-dpp-btn-arrow { background: #f9fafb !important; color: #111827 !important; }
    html.dark .ss-dpp-btn:hover .ss-dpp-btn-arrow { background: #ffffff !important; color: #6366f1 !important; transform: translateX(2px) !important; }

    #settBd { position: absolute; inset: 0; z-index: 25; display: none; pointer-events: auto; }
    #settBd.on { display: block; }
    #settPanel { position: absolute; bottom: 80px; right: 8px; width: min(280px, calc(100% - 16px)); z-index: 30; display: none; pointer-events: auto; }
    #settPanel.on { display: block; }
    .sc { background: #0a0a0a; border-radius: 6px; overflow: hidden; border: 1px solid rgba(255,255,255,.08); box-shadow: 0 8px 32px rgba(0,0,0,.9); }
    .s-main { display: flex; flex-direction: column; }
    .s-main.off { display: none; }
    .s-sub { display: none; flex-direction: column; }
    .s-sub.on { display: flex; }
    .s-row { display: flex; align-items: center; justify-content: space-between; padding: 16px 18px; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,.04); transition: background .15s; }
    .s-row:last-child { border-bottom: none; }
    .s-row:hover, .sopt:hover { background: rgba(255,255,255,.05); }
    .s-row:active { background: rgba(99,102,241,.12); }
    .s-lbl { color: #fff; font-size: 14px; font-weight: 600; letter-spacing: .3px; }
    .s-val { display: flex; align-items: center; gap: 6px; color: rgba(255,255,255,.5); font-size: 13px; font-weight: 600; }
    .s-back { display: flex; align-items: center; gap: 12px; padding: 14px 18px; border-bottom: 1px solid rgba(255,255,255,.06); cursor: pointer; background: rgba(99,102,241,.08); transition: background .15s; }
    .s-back:hover { background: rgba(99,102,241,.15); }
    .s-back span { color: #fff; font-size: 14px; font-weight: 700; letter-spacing: .5px; }
    .s-scroll { overflow-y: auto; max-height: 240px; }
    .sopt { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,.03); transition: background .15s; }
    .sopt:last-child { border-bottom: none; }
    .sopt.active { background: rgba(99,102,241,.15); border-left: 3px solid var(--accent); }
    .radio { width: 20px; height: 20px; border-radius: 50% !important; border: 2px solid rgba(255,255,255,.25); display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: all .2s; }
    .sopt.active .radio { border-color: var(--accent); background: var(--accent); }
    .rdot { width: 8px; height: 8px; border-radius: 50% !important; background: #fff; display: none; }
    .sopt.active .rdot { display: block; }

    /* --- Ask AI Panel & Button Styles --- */
    .askai-gradient-wrapper {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 24px !important;
      padding: 0 !important;
      background: none !important;
      transition: all 0.2s ease-in-out;
      margin-right: 8px;
    }
    #askAiIcon:hover {
      transform: scale(1.03);
      box-shadow: 0 0 18px rgba(83, 177, 253, 0.45) !important;
    }
    #askAiIcon {
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 24px !important;
      cursor: pointer;
      background-color: #000 !important;
      background: #000 !important;
      gap: 8px !important;
      padding: 5px 14px !important;
      border: 2px solid transparent !important;
      background-image: linear-gradient(#000, #000), linear-gradient(to right, #53B1FD, #FDA29B) !important;
      background-origin: border-box !important;
      background-clip: padding-box, border-box !important;
      overflow: hidden;
      outline: none;
      transition: transform 0.2s, box-shadow 0.2s !important;
      flex-shrink: 0 !important;
      margin-right: 8px !important;
    }
    #askAiIcon img {
      width: 22px;
      height: 22px;
      flex-shrink: 0;
    }
    #askAiIcon span {
      color: #D9D9DA;
      font-weight: 700 !important;
      font-size: 13.5px !important;
      white-space: nowrap;
      letter-spacing: 0px;
    }
    @media (max-width: 768px) {
      #askAiIcon span {
        display: none !important;
      }
      #askAiIcon {
        padding: 0 !important;
        width: 32px !important;
        height: 32px !important;
        border-radius: 50% !important;
        display: flex !important; /* Always show Ask AI button */
      }
      .askai-gradient-wrapper {
        display: inline-flex !important; /* Always show Ask AI wrapper */
        margin-right: 6px !important;
      }
      #shell.layout-mobile #askAiIcon {
        display: flex !important; /* Always show in mobile layout */
      }
      #bottom-panel.open {
        height: 55vh !important;
        max-height: calc(100dvh - 120px) !important;
      }
      .askai-input-wrapper {
        padding-bottom: calc(12px + env(safe-area-inset-bottom)) !important;
      }
      /* Hide playlist button on mobile layout unless in fullscreen mode */
      #shell.layout-mobile #playlistPanelBtn {
        display: none !important;
      }
      #shell.layout-mobile.is-fullscreen #playlistPanelBtn {
        display: flex !important;
      }
    }

    @media (max-width: 480px) {
      .askai-gradient-wrapper {
        padding: 1px !important;
        margin-right: 4px !important;
      }
      #askAiIcon {
        width: 28px !important;
        height: 28px !important;
      }
      #askAiIcon img {
        width: 18px !important;
        height: 18px !important;
      }
    }

    /* On mobile/smaller screens in landscape, only show when player is fullscreen, except on layout-mobile where Ask AI is always shown */
    @media (max-width: 950px) and (orientation: landscape) {
      #askAiIcon {
        display: none !important;
      }
      .askai-gradient-wrapper {
        display: none !important;
      }
      #shell.is-fullscreen #askAiIcon,
      #shell.layout-mobile #askAiIcon {
        display: flex !important;
      }
      #shell.is-fullscreen .askai-gradient-wrapper,
      #shell.layout-mobile .askai-gradient-wrapper {
        display: inline-flex !important;
      }
    }
    @media (min-width: 1280px) {
      #askAiIcon {
        padding: 5px 14px !important;
        gap: 8px !important;
      }
      #askAiIcon img {
        width: 24px;
        height: 24px;
      }
      #askAiIcon span {
        font-size: 13.5px !important;
      }
    }

    .type-askai-container {
      display: flex !important;
      flex-direction: column !important;
      height: calc(100% - 50px) !important;
      background-color: #fafbfc !important;
      background-image: radial-gradient(#dcf0fa 1px, transparent 0), radial-gradient(#dcf0fa 1px, #fafbfc 0) !important;
      background-size: 20px 20px !important;
      background-position: 0 0, 10px 10px !important;
      color: #1a1a24 !important;
      position: relative !important;
      padding: 0 !important;
    }

    .askai-chat-box {
      flex: 1 !important;
      overflow-y: auto !important;
      padding: 16px !important;
      display: flex !important;
      flex-direction: column !important;
      gap: 16px !important;
    }
    .askai-chat-box::before {
      content: "" !important;
      margin-top: auto !important;
    }

    .askai-msg-row {
      display: flex !important;
      align-items: flex-end !important;
      gap: 10px !important;
      max-width: 95% !important;
    }
    .askai-msg-row.ai {
      align-self: flex-start !important;
    }
    .askai-msg-row.user {
      align-self: flex-end !important;
      flex-direction: row-reverse !important;
    }

    .askai-avatar {
      width: 34px !important;
      height: 34px !important;
      border-radius: 50% !important;
      background: #e0ebff !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      flex-shrink: 0 !important;
      border: 1px solid #c2dbff !important;
      overflow: hidden !important;
    }
    .askai-avatar img {
      width: 100% !important;
      height: 100% !important;
      object-fit: cover !important;
    }

    .askai-msg-bubble {
      padding: 12px 16px !important;
      border-radius: 16px !important;
      font-size: 14px !important;
      line-height: 1.5 !important;
      position: relative !important;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06) !important;
      word-break: break-word !important;
      font-weight: 500 !important;
    }
    .askai-msg-row.ai .askai-msg-bubble {
      background: #e8f0fe !important;
      color: #1a365d !important;
      border-bottom-left-radius: 4px !important;
      border-top-left-radius: 16px !important;
      border: 1px solid #d2e3fc !important;
    }
    .askai-msg-row.user .askai-msg-bubble {
      background: #3b82f6 !important;
      color: #ffffff !important;
      border-bottom-right-radius: 4px !important;
      border-top-right-radius: 16px !important;
    }

    .askai-msg-actions {
      display: flex !important;
      align-items: center !important;
      gap: 8px !important;
      flex-shrink: 0 !important;
      align-self: flex-end !important;
    }
    .askai-action-btn {
      background: none !important;
      border: none !important;
      color: #9ba3b2 !important;
      cursor: pointer !important;
      padding: 4px !important;
      border-radius: 6px !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      transition: color 0.15s ease, background 0.15s ease !important;
      outline: none !important;
    }
    .askai-action-btn:hover {
      color: #5A4BDA !important;
      background: rgba(90,75,218,0.08) !important;
    }
    .askai-action-btn.active {
      color: #5A4BDA !important;
    }

    .askai-preview-box {
      display: none;
      position: absolute !important;
      z-index: 99 !important;
      width: 100px !important;
      height: 62px !important;
      background: transparent !important;
      border: none !important;
      padding: 0 !important;
      margin: 0 !important;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25) !important;
      border-radius: 8px !important;
    }
    @keyframes askai-preview-slide-up {
      0% {
        transform: translateY(12px) scale(0.9);
        opacity: 0;
      }
      100% {
        transform: translateY(0) scale(1);
        opacity: 1;
      }
    }
    .askai-preview-box.slide-up-active {
      display: flex !important;
      animation: askai-preview-slide-up 0.25s cubic-bezier(0.34, 1.56, 0.64, 1) forwards !important;
    }
    .askai-preview-thumb-wrap {
      width: 100% !important;
      height: 100% !important;
      position: relative !important;
      display: block !important;
    }
    @keyframes skeleton-loading {
      0% { background-position: -200% 0; }
      100% { background-position: 200% 0; }
    }
    .askai-preview-thumb {
      width: 100% !important;
      height: 100% !important;
      border-radius: 8px !important;
      object-fit: cover !important;
      border: none !important;
      background: #000 !important;
      box-sizing: border-box !important;
      transition: all 0.2s ease !important;
    }
    .askai-preview-thumb.loading {
      background: linear-gradient(90deg, #1b2124 25%, #2a353d 50%, #1b2124 75%) !important;
      background-size: 200% 100% !important;
      animation: skeleton-loading 1.5s infinite !important;
    }
    .askai-preview-time {
      display: none !important;
    }
    .askai-preview-remove {
      position: absolute !important;
      top: -8px !important;
      right: -8px !important;
      background: rgba(0, 0, 0, 0.75) !important;
      border: 1px solid rgba(255, 255, 255, 0.8) !important;
      color: #ffffff !important;
      cursor: pointer !important;
      font-size: 10px !important;
      width: 18px !important;
      height: 18px !important;
      border-radius: 50% !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      transition: all 0.2s !important;
      outline: none !important;
      z-index: 100 !important;
      padding: 0 !important;
      line-height: 1 !important;
    }
    .askai-preview-remove:hover {
      background: #ef4444 !important;
      border-color: #ffffff !important;
      transform: scale(1.1);
    }

    .askai-input-wrapper {
      background: #ffffff !important;
      border-top: 1px solid #ebebf0 !important;
      padding: 10px 14px !important;
      box-sizing: border-box !important;
      flex-shrink: 0 !important;
    }

    .askai-mode-toggle-btn {
      width: 44px !important;
      height: 44px !important;
      border-radius: 50% !important;
      background: #F0F0F5 !important;
      border: none !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      cursor: pointer !important;
      flex-shrink: 0 !important;
      transition: background 0.2s ease !important;
      outline: none !important;
    }
    .askai-mode-toggle-btn:hover {
      background: #E4E2F8 !important;
    }

    .askai-controls-voice {
      display: flex !important;
      align-items: center !important;
      justify-content: space-between !important;
      padding: 4px 24px !important;
      width: 100% !important;
      box-sizing: border-box !important;
      gap: 0 !important;
    }
    .askai-circle-btn {
      width: 48px !important;
      height: 48px !important;
      border-radius: 50% !important;
      background: #f7fafc !important;
      border: 1px solid #e2e8f0 !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      cursor: pointer !important;
      color: #4a5568 !important;
      transition: all 0.2s !important;
      outline: none !important;
    }
    .askai-circle-btn:hover {
      background: #edf2f7 !important;
      color: #1a202c !important;
    }
    .askai-circle-btn.mic-large {
      width: 64px !important;
      height: 64px !important;
      background: linear-gradient(135deg, #3b82f6, #8b5cf6) !important;
      color: #ffffff !important;
      border: none !important;
      box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4) !important;
    }
    .askai-circle-btn.mic-large:hover {
      transform: scale(1.05) !important;
      box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5) !important;
    }
    .askai-circle-btn.mic-large.recording {
      animation: askai-pulse 1.5s infinite !important;
      background: #ef4444 !important;
      box-shadow: 0 4px 14px rgba(239, 68, 68, 0.4) !important;
    }

    .askai-controls-keyboard {
      display: flex !important;
      align-items: center !important;
      gap: 12px !important;
    }
    .askai-input-field-wrap {
      flex: 1 !important;
      position: relative !important;
      display: flex !important;
      align-items: center !important;
    }
    .askai-input-field {
      width: 100% !important;
      padding: 12px 40px 12px 16px !important;
      border: 1px solid #cbd5e0 !important;
      border-radius: 8px !important;
      font-size: 14px !important;
      background: #f7fafc !important;
      color: #1a202c !important;
      outline: none !important;
      transition: all 0.15s ease !important;
      box-sizing: border-box !important;
    }
    .askai-input-field:focus {
      border-color: #5A4BDA !important;
      background: #ffffff !important;
      box-shadow: 0 0 0 3px rgba(90, 75, 218, 0.15) !important;
    }
    .askai-input-mic-btn {
      position: absolute !important;
      right: 10px !important;
      top: 50% !important;
      transform: translateY(-50%) !important;
      width: 32px !important;
      height: 32px !important;
      background: none !important;
      border: none !important;
      color: #5A4BDA !important;
      cursor: pointer !important;
      padding: 4px !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      outline: none !important;
      transition: color 0.2s ease, transform 0.2s ease !important;
      z-index: 10 !important;
    }
    .askai-input-mic-btn:hover {
      color: #5A4BDA !important;
      transform: translateY(-50%) scale(1.1) !important;
    }
    .askai-input-mic-btn.recording {
      color: #ef4444 !important;
      animation: askai-pulse-light 1.5s infinite !important;
    }

    .askai-input-send-btn {
      position: absolute !important;
      right: 6px !important;
      top: 50% !important;
      transform: translateY(-50%) !important;
      width: 32px !important;
      height: 32px !important;
      border-radius: 50% !important;
      background: #5A4BDA !important;
      color: #ffffff !important;
      border: none !important;
      display: none;
      align-items: center !important;
      justify-content: center !important;
      cursor: pointer !important;
      transition: background 0.15s ease, transform 0.15s ease !important;
      outline: none !important;
      flex-shrink: 0 !important;
      box-sizing: border-box !important;
      padding: 0 !important;
    }
    .askai-input-send-btn:hover {
      background: #493cbd !important;
      transform: translateY(-50%) scale(1.05) !important;
    }

    .askai-voice-container {
      display: flex !important;
      flex-direction: column !important;
      align-items: center !important;
      width: 100% !important;
      padding: 10px 0 !important;
      box-sizing: border-box !important;
    }
    textarea.askai-voice-status {
      font-size: 15px !important;
      font-weight: 600 !important;
      color: #1A2124 !important;
      margin-bottom: 24px !important;
      text-align: center !important;
      width: 100% !important;
      min-height: 80px !important;
      max-height: 150px !important;
      line-height: 1.6 !important;
      padding: 0 16px !important;
      box-sizing: border-box !important;
      background: transparent !important;
      border: none !important;
      outline: none !important;
      resize: none !important;
      font-family: inherit !important;
      transition: color 0.2s ease !important;
      overflow-y: auto !important;
    }
    textarea.askai-voice-status::-webkit-scrollbar {
      width: 6px !important;
    }
    textarea.askai-voice-status::-webkit-scrollbar-track {
      background: transparent !important;
    }
    textarea.askai-voice-status::-webkit-scrollbar-thumb {
      background: rgba(90, 75, 218, 0.2) !important;
      border-radius: 3px !important;
    }
    textarea.askai-voice-status::-webkit-scrollbar-thumb:hover {
      background: rgba(90, 75, 218, 0.4) !important;
    }
    textarea.askai-voice-status::placeholder {
      color: #718096 !important;
      font-weight: 500 !important;
      font-size: 14px !important;
    }
    .askai-voice-delete-btn {
      width: 52px !important;
      height: 52px !important;
      border-radius: 50% !important;
      background: #FEE2E2 !important;
      border: none !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      cursor: pointer !important;
      transition: background 0.2s ease, transform 0.1s ease !important;
      outline: none !important;
    }
    .askai-voice-delete-btn:hover {
      background: #FCA5A5 !important;
      transform: scale(1.05) !important;
    }
    .askai-voice-main-mic-btn {
      width: 64px !important;
      height: 64px !important;
      border-radius: 50% !important;
      background: #5A4BDA !important;
      border: none !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      cursor: pointer !important;
      transition: background 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease !important;
      outline: none !important;
      box-shadow: 0 4px 12px rgba(90, 75, 218, 0.3) !important;
    }
    .askai-voice-main-mic-btn:hover {
      background: #493cbd !important;
      transform: scale(1.05) !important;
      box-shadow: 0 6px 16px rgba(90, 75, 218, 0.4) !important;
    }
    .askai-voice-main-mic-btn.recording {
      background: #5A4BDA !important;
      animation: askai-pulse-purple 1.5s infinite !important;
    }
    .askai-voice-keyboard-btn {
      width: 52px !important;
      height: 52px !important;
      border-radius: 50% !important;
      background: #EAECEF !important;
      border: none !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      cursor: pointer !important;
      transition: background 0.2s ease, transform 0.1s ease !important;
      outline: none !important;
    }
    .askai-voice-keyboard-btn:hover {
      background: #D1D5DB !important;
      transform: scale(1.05) !important;
    }

    @keyframes askai-pulse-purple {
      0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(90, 75, 218, 0.7); }
      70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(90, 75, 218, 0); }
      100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(90, 75, 218, 0); }
    }

    .askai-crop-btn-purple {
      border-radius: 26px !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      height: 52px !important;
      min-width: 52px !important;
      padding: 0 16px !important;
      background: #F1EFFF !important;
      border: none !important;
      cursor: pointer !important;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
      outline: none !important;
      flex-shrink: 0 !important;
      box-sizing: border-box !important;
    }
    .askai-crop-btn-purple:hover {
      background: #e4e2fc !important;
    }
    .askai-crop-btn-purple.active {
      background-color: #5A4BDA !important;
      background: #5A4BDA !important;
      color: #ffffff !important;
    }
    .askai-crop-btn-purple.active svg path:first-of-type {
      fill: #ffffff !important;
    }
    .askai-crop-btn-purple.active svg line {
      stroke: #ffffff !important;
    }
    .askai-crop-btn-purple.active svg path:last-of-type {
      fill: #ffffff !important;
      stroke: #5A4BDA !important;
    }
    .askai-crop-btn-purple.active span {
      color: #ffffff !important;
      opacity: 1 !important;
      max-w: 120px !important;
    }

    .askai-action-btn.speaker.speaker-on svg {
      stroke: #5A4BDA !important;
      fill: rgba(90, 75, 218, 0.1) !important;
    }
    .askai-action-btn.speaker.speaker-off svg {
      stroke: #718096 !important;
      fill: none !important;
    }
    .askai-action-btn.speaker.active svg {
      stroke: #5A4BDA !important;
      fill: #5A4BDA !important;
      animation: askai-pulse-light 1.5s infinite !important;
    }

    .askai-input-stop-btn {
      width: 48px !important;
      height: 48px !important;
      border-radius: 50% !important;
      background: none !important;
      border: none !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      cursor: pointer !important;
      transition: transform 0.15s ease !important;
      outline: none !important;
      flex-shrink: 0 !important;
      padding: 0 !important;
    }
    .askai-input-stop-btn:hover {
      transform: scale(1.05) !important;
    }

    .askai-typing-indicator {
      display: flex !important;
      align-items: center !important;
      gap: 5px !important;
      padding: 6px 10px !important;
      min-height: 18px !important;
    }
    .askai-typing-indicator span {
      width: 6px !important;
      height: 6px !important;
      background-color: #5A4BDA !important;
      border-radius: 50% !important;
      display: inline-block !important;
      animation: askai-bounce 1.4s infinite ease-in-out both !important;
    }
    .askai-typing-indicator span:nth-child(1) {
      animation-delay: -0.32s !important;
    }
    .askai-typing-indicator span:nth-child(2) {
      animation-delay: -0.16s !important;
    }
    @keyframes askai-bounce {
      0%, 80%, 100% { transform: scale(0.3); opacity: 0.4; }
      40% { transform: scale(1.0); opacity: 1; }
    }

    @keyframes askai-pulse {
      0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
      70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
      100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }
    @keyframes askai-pulse-light {
      0% { transform: scale(1); }
      50% { transform: scale(1.2); }
      100% { transform: scale(1); }
    }
    @keyframes askai-spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    .askai-thinking-container {
      display: inline-flex;
      align-items: center;
      user-select: none;
      padding: 2px 0;
    }
    .askai-thinking-wave {
      display: inline-flex;
      font-size: 14px;
      font-weight: 600;
      letter-spacing: 0.2px;
    }
    .askai-thinking-wave span {
      display: inline-block;
      color: #707f98;
      animation: askai-wave-anim 1.8s infinite cubic-bezier(.22,.61,.36,1);
    }
    .askai-thinking-wave span:nth-child(1) { animation-delay: 0.0s; }
    .askai-thinking-wave span:nth-child(2) { animation-delay: 0.12s; }
    .askai-thinking-wave span:nth-child(3) { animation-delay: 0.24s; }
    .askai-thinking-wave span:nth-child(4) { animation-delay: 0.36s; }
    .askai-thinking-wave span:nth-child(5) { animation-delay: 0.48s; }
    .askai-thinking-wave span:nth-child(6) { animation-delay: 0.60s; }
    .askai-thinking-wave span:nth-child(7) { animation-delay: 0.72s; }
    .askai-thinking-wave span:nth-child(8) { animation-delay: 0.84s; }
    .askai-thinking-wave span:nth-child(9) { animation-delay: 0.96s; }
    .askai-thinking-wave span:nth-child(10) { animation-delay: 1.08s; }
    .askai-thinking-wave span:nth-child(11) { animation-delay: 1.20s; }
    @keyframes askai-wave-anim {
      0%, 100% {
        color: #707f98;
        transform: translateY(0);
      }
      18% {
        color: #1a73e8;
        transform: translateY(-4px);
        font-weight: 700;
      }
      32% {
        color: #3c4043;
        transform: translateY(-2px);
      }
      46% {
        color: #5f6368;
        transform: translateY(-1px);
      }
    }
  </style>
</head>
<body class="">

<div id="shell" class="layout-desktop">
  <input type="hidden" id="batch_id" value="">
  <input type="hidden" id="subject_id" value="">
  <input type="hidden" id="topic_id" value="">
  <input type="hidden" id="video_id" value="">
  <input type="hidden" id="video_name" value="">
  <input type="hidden" id="video_img" value="">
  <input type="hidden" id="video_type" value="">
  <input type="hidden" id="play_type" value="Lecture">
  <input type="hidden" id="subject_name" value="">
  <input type="hidden" id="topic_name" value="">
  
  <input type="hidden" id="cookie_token" value="Qd2wfhzRoi5eQdoITwpbNKPMdMTNSs37YUjvj0rSb5sGwlFOxcRHjSW3cSaE0hDargtkzHogO/XCoOepdjgGVn6vKXwwkEBQ0LEeVF3xw5mWOqU0fUrpSLrEylkahzdLq0/7TPYvT0Q88xlWOFi1FHb4d5H/CeBfUwq54G0FFxudvhdnf0ntkdQ1FgCrJbdfBQ4uC7QJ1PkvxEvfhSvMyetKDLG/jxNAKxFv7ZpjiVotx3pfH/lRr+rAzpYT0b9OGbIpopovmyesksoCJ5Dc2QRIao24s/FCD8Nwnp2tNfvJdcxsC7fiBuUNQKmk6Ts3Q3+oOchHU+FvPZY13DXU2aMOWcnjXIcX/bOUlOMk2oRub70L9X2BpK7Qj7UMIN9BFvVTrY1+OHWS1JIs3K2ogxIY7IYydFA/B3jcjOkxMTzzhW8eq3veec18md/D+MEkdJs0/x5F4dZyZZoPhc2EM1cf0DUVZdGKV+dJ8okpndIra/KnW0XMn/Xi9JXU5Z9MbKiCvysOR01GQ6NGd7nnqMiGwPxe/EneKLuqHDITgzbowBIEhAhehPWguOvXmcWrMFqCBtmJ3JDyFOrR7E9fvO821K3WDYoxv9bz2F33W1QpsRyiTEKX9p/OeiBcoNKRqm/Y7HWr8c3NKEqioXVFZa3G6OJouo0rKZI/+2emJFUuZG4Yn6gnbVyLa8xo6YmAcnK2KDiWSasGTELD4qVPmeiuG+8lP/taveK+35E6Bj8AGCBt6aM5jP/AZGrEB3T/35HNc+HIYLAIrsa7IeaN+ry8AX0nmjySMODfJKrJUr4=">
  <input type="hidden" id="attachments_data" value="{&quot;success&quot;:false,&quot;notes&quot;:[],&quot;dpp_pdf&quot;:[],&quot;exercise&quot;:[]}">

  <input type="hidden" id="enc_video_url"   value="">
  <input type="hidden" id="enc_key_id"      value="">
  <input type="hidden" id="enc_key_value"   value="">
  <input type="hidden" id="enc_license_url" value="">
  <input type="hidden" id="enc_drm_type"    value="">
  <input type="hidden" id="videoDetailsId"  value="">

  <div id="main-row">
    <div id="video-col">
      <div id="vid-box">
                    <video id="vid" playsinline preload="auto" crossorigin="anonymous"></video>
            <div class="ov" id="ovLoad"><div class="spinner"></div><p id="loadMsg">Loading Lecture…</p></div>
            <div class="ov" id="ovErr"><span style="font-size:28px">⚠️</span><p id="errMsg">Something went wrong.</p><button id="retryBtn">Retry</button></div>
            <div id="bufSpin"></div>
              </div>

      <div id="tap-shield"></div>

      <div id="lock-overlay-shield">
         <button class="cbtn" id="dedicatedLockBtn" style="background: rgba(0,0,0,0.65); border-radius: 50%; box-shadow: 0 4px 12px rgba(0,0,0,0.4);">
            <svg id="dedicatedLockIconSvg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 9.9-1"></path>
            </svg>
         </button>
      </div>

      <div id="ctrl-overlay" class="show-controls">
        <button id="centerPlayBtn" class="mobile-center-play">
          <svg id="cPlayIcon" width="56" height="56" viewBox="0 0 24 24" fill="white"><path d="M4.5 5.653c0-1.427 1.529-2.33 2.779-1.643l11.54 6.347c1.295.712 1.295 2.573 0 3.286L7.28 19.99c-1.25.687-2.779-.217-2.779-1.643V5.653Z"/></svg>
          <svg id="cPauseIcon" width="56" height="56" viewBox="0 0 24 24" fill="white" style="display:none"><path fill-rule="evenodd" d="M6.75 5.25a.75.75 0 01.75-.75H9a.75.75 0 01.75.75v13.5a.75.75 0 01-.75.75H7.5a.75.75 0 01-.75-.75V5.25zm7.5 0A.75.75 0 0115 4.5h1.5a.75.75 0 01.75.75v13.5a.75.75 0 01-.75.75H15a.75.75 0 01-.75-.75V5.25z" clip-rule="evenodd"/></svg>
        </button>
        <div id="player-header">
          <button class="hdr-back" onclick="if (!window.__cfRLUnblockHandlers) return false; history.back()" data-cf-modified-5803c15b1d9a62bafdae863a-="">
            <svg viewBox="0 0 24 24"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
          </button>
          <div id="player-title"></div>
          
          <div class="header-menu-container">
            <button class="cbtn" id="threeDotsMenuBtn" style="margin-left: auto;">
              <svg viewBox="0 0 24 24" fill="white" width="26" height="26">
                <path fill-rule="evenodd" d="M10.5 6a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0Zm0 6a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0Zm0 6a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0Z" clip-rule="evenodd" />
              </svg>
            </button>
            <div class="dropdown-menu-list" id="threeDotsDropdown">
              <button class="dropdown-item-btn" id="menuAttachmentAction">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M18.7455 5.25444C17.3396 3.84855 15.0602 3.84855 13.6543 5.25444L5.25432 13.6544C3.84843 15.0603 3.84843 17.3397 5.25432 18.7456C6.65985 20.1511 8.93843 20.1515 10.3444 18.7467C10.3448 18.7463 10.3451 18.746 10.3455 18.7456L10.9414 18.1457C11.2917 17.7931 11.8616 17.7912 12.2142 18.1415C12.5668 18.4918 12.5687 19.0617 12.2184 19.4143L11.6204 20.0163L11.6183 20.0184C9.50945 22.1272 6.09036 22.1272 3.98153 20.0184C1.87269 17.9096 1.87269 14.4905 3.98153 12.3816L12.3815 3.98165C14.4904 1.87282 17.9094 1.87282 20.0183 3.98165C22.1255 6.08891 22.1271 9.50448 20.023 11.6137L15.8774 15.8775C14.6472 17.1076 12.6527 17.1076 11.4225 15.8774C10.1924 14.6473 10.1924 12.6528 11.4225 11.4227L15.5635 7.28168C15.915 6.9302 16.4848 6.9302 16.8363 7.28168C17.1878 7.63315 17.1878 8.203 16.8363 8.55447L12.6953 12.6955C12.1681 13.2227 12.1681 14.0774 12.6953 14.6046C13.2212 15.1305 14.073 15.1318 14.6006 14.6086L18.7454 10.3455C20.1513 8.93966 20.1514 6.66033 18.7455 5.25444Z" fill="currentColor"/></svg>
                <span>Attachments</span>
              </button>
              <button class="dropdown-item-btn" id="menuTimelineAction">
                <svg width="18" height="18" viewBox="0 0 40 40" fill="white"><path d="M5.1 10a.5.5 0 0 1 .5-.5h.2a.5.5 0 0 1 .5.5v20a.5.5 0 0 1-.5.5h-.2a.5.5 0 0 1-.5-.5V10Z"/><rect x="10.3" y="10.3" width="19.4" height="19.4" rx="1.2" stroke="white" stroke-width="1.8" fill="none"/><path d="M17.2 17l5.2 3-5.2 3V17Z"/></svg>
                <span>Timeline Slides</span>
              </button>
              <button class="dropdown-item-btn" id="menuPlaylistAction">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                <span>Playlist</span>
              </button>
            </div>
          </div>
        </div>

        <div id="ctrl-mid"></div>

        <div id="player-footer">
          <div class="prog-wrap">
            <div class="time-row">
              <div class="t-left">
                <span class="t-time" id="curTime">0:00</span>
                <span class="spd-badge" id="spdBadge">1x</span>
                <div class="live-badge" id="liveBadge"><div class="live-dot"></div><span id="liveText">LIVE</span></div>
              </div>
              <span class="t-time" id="durTime">0:00</span>
            </div>
            <div class="bar-wrap">
              <div class="bar-bg">
                <div class="bar-buf" id="barBuf" style="width:0%"></div>
                <div class="bar-fill" id="barFill" style="width:0%"></div>
              </div>
              <div class="bar-thumb" id="barThumb" style="left:0%"></div>
              <input type="range" class="bar-input" id="seekBar" min="0" max="1000" step="1" value="0">
            </div>
          </div>
          <div class="btn-row">
            <div class="btn-l">
              <button class="cbtn" id="playBtn">
                <svg id="iPlay" width="34" height="34" viewBox="0 0 24 24" fill="white"><path d="M4.5 5.653c0-1.427 1.529-2.33 2.779-1.643l11.54 6.347c1.295.712 1.295 2.573 0 3.286L7.28 19.99c-1.25.687-2.779-.217-2.779-1.643V5.653Z"/></svg>
                <svg id="iPause" width="34" height="34" viewBox="0 0 24 24" fill="white" style="display:none"><path fill-rule="evenodd" d="M6.75 5.25a.75.75 0 01.75-.75H9a.75.75 0 01.75.75v13.5a.75.75 0 01-.75.75H7.5a.75.75 0 01-.75-.75V5.25zm7.5 0A.75.75 0 0115 4.5h1.5a.75.75 0 01.75.75v13.5a.75.75 0 01-.75.75H15a.75.75 0 01-.75-.75V5.25z" clip-rule="evenodd"/></svg>
              </button>
              <button class="cbtn" id="rwBtn"></button>
              <button class="cbtn" id="fwBtn"></button>
              <div class="vol-grp">
                <button class="cbtn" id="muteBtn">
                  <svg id="volIcon" width="28" height="28" viewBox="0 0 24 24" fill="white">
                    <path d="M13.5 4.06c0-1.336-1.616-2.005-2.56-1.06l-4.5 4.5H4.508c-1.141 0-2.318.664-2.66 1.905A9.76 9.76 0 0 0 1.5 12c0 .898.121 1.768.35 2.595.341 1.24 1.518 1.905 2.659 1.905h1.93l4.5 4.5c.945.945 2.561.276 2.561-1.06V4.06ZM18.584 5.106a.75.75 0 0 1 1.06 0c3.808 3.807 3.808 9.98 0 13.788a.75.75 0 0 1-1.06-1.06 8.25 8.25 0 0 0 0-11.668.75.75 0 0 1 0-1.06Z"/>
                    <path d="M15.932 7.757a.75.75 0 0 1 1.061 0 6 6 0 0 1 0 8.486.75.75 0 0 1-1.06-1.061 4.5 4.5 0 0 0 0-6.364.75.75 0 0 1 0-1.06Z" fill="white"/>
                  </svg>
                </button>
                <input type="range" class="vol-range" id="volBar" min="0" max="100" value="100">
              </div>
            </div>
            <div class="btn-r">
              <!-- Gradient Border -->
              <div class="askai-gradient-wrapper">
                  <!-- Actual Button -->
                  <button
                      id="askAiIcon"
                      title="Ask AI"
                      aria-label="Ask AI Assistant"
                  >
                      <img
                          src="https://www.pw.live/watch/static/media/AiGuruIcon.44436f4a1c98a99e5b0e4070df7532b8.svg"
                          alt="AI Guru"
                      >
                      <span class="hidden xl:block">
                          Ask AI
                      </span>
                  </button>
              </div>
              <button class="cbtn" id="playlistPanelBtn" style="color:#fff; margin-right: 4px;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
              </button>
              <button class="cbtn" id="slidesPanelBtn" style="color:#fff;">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none"><path d="M5.1 10a.5.5 0 0 1 .5-.5h.2a.5.5 0 0 1 .5.5v20a.5.5 0 0 1-.5.5h-.2a.5.5 0 0 1-.5-.5V10Z" fill="white"/><rect x="10.3" y="10.3" width="19.4" height="19.4" rx="1.2" stroke="white" stroke-width="1.8" fill="none"/><path d="M17.2 17l5.2 3-5.2 3V17Z" fill="white"/><path d="M33.7 10a.5.5 0 0 1 .5-.5h.2a.5.5 0 0 1 .5.5v20a.5.5 0 0 1-.5.5h-.2a.5.5 0 0 1-.5-.5V10Z" fill="white"/></svg>
              </button>
              <button class="cbtn" id="settBtn">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
              <button class="cbtn" id="fsBtn">
                <svg id="iFs" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round">
                  <path d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <div id="settBd"></div>
        <div id="settPanel">
          <div class="sc">
            <div class="s-main" id="sMain">
              <div class="s-row" id="sSpeedRow">
                <span class="s-lbl">Speed</span>
                <div class="s-val">
                  <span id="sSpeedVal">Normal</span>
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 512" style="height: 10px; fill: currentColor;"><path d="M247.1 233.4c12.5 12.5 12.5 32.8 0 45.3l-160 160c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3L179.2 256 41.9 118.6c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0l160 160z"/></svg>
                </div>
              </div>
              <div class="s-row" id="sQualRow">
                <span class="s-lbl">Quality</span>
                <div class="s-val">
                  <span id="sQualVal">Auto</span>
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 512" style="height: 10px; fill: currentColor;"><path d="M247.1 233.4c12.5 12.5 12.5 32.8 0 45.3l-160 160c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3L179.2 256 41.9 118.6c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0l160 160z"/></svg>
                </div>
              </div>
            </div>
            
            <div class="s-sub" id="sSpeedSub">
              <div class="s-back" id="sSpeedBack">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
                <span>Playback Speed</span>
              </div>
              <div class="s-scroll" id="sSpeedOpts"></div>
            </div>
            
            <div class="s-sub" id="sQualSub">
              <div class="s-back" id="sQualBack">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
                <span>Video Quality</span>
              </div>
              <div class="s-scroll" id="sQualOpts"></div>
            </div>
          </div>
        </div>
      </div></div><div id="side-panel"><div class="panel-inner" id="panelInner"></div></div>
  </div><div id="bottom-panel"><div class="panel-inner" id="panelInnerMob"></div></div>
</div>

<script data-cfasync="false">
window.APP_CONFIG = {
  payloadEncrypted: true,  // true = decrypt payload, false = plain JS / plain values
  enableAskAi: false,       // true = show Ask AI, false = hide Ask AI
  enableTimeline: true,    // true = show Timeline slides, false = hide Timeline slides
  enableAttachments: true, // true = show Attachments, false = hide Attachments
  enablePlaylist: true,    // true = show Playlist, false = hide Playlist
  speedOptions: [0.5, 0.6, 0.7, 0.8, 0.9, 1, 1.1, 1.25, 1.3, 1.4, 1.5, 1.75, 1.9, 2, 2.25, 2.5, 2.75, 3, 3.25, 3.5, 3.75, 4, 4.5, 5, 5.5]
};

</script>
<script data-cfasync="false" src="shree-radhe-video-script.js?v=264"></script>

<foreignObject><script src="/cdn-cgi/scripts/7d0fa10a/cloudflare-static/rocket-loader.min.js" data-cf-settings="5803c15b1d9a62bafdae863a-|49" defer></script></foreignObject><script defer src="https://static.cloudflareinsights.com/beacon.min.js/v4513226cdae34746b4dedf0b4dfa099e1781791509496" integrity="sha512-ZE9pZaUXND66v380QUtch/5sE9tPFh2zg45pR2PB0CVkCtOREv2AJKkSidISWkysEuQ0EH8faUU5du78bx87UQ==" data-cf-beacon='{"version":"2024.11.0","token":"fe6008919773468a8c3259dbe83eef30","r":1,"server_timing":{"name":{"cfCacheStatus":true,"cfEdge":true,"cfExtPri":true,"cfL4":true,"cfOrigin":true,"cfSpeedBrain":true},"location_startswith":null}}' crossorigin="anonymous"></script>
</body>
</html>
