/** ChatMe Widget v1.0 - Embeddable Chatbot */
(function() {
  'use strict';
  
  var config = window.ChatMeConfig || {};
  if (!config.apiKey) return;
  if (document.getElementById('chatme-root')) return;
  
  config.apiUrl = config.apiUrl || '';
  config.primaryColor = config.primaryColor || '#4F46E5';
  config.secondaryColor = config.secondaryColor || '#ffffff';
  config.botName = config.botName || 'Pembantu ChatMe';
  config.welcomeMessage = config.welcomeMessage || 'Helo! Bagaimana saya boleh membantu anda?';
  config.placeholderText = config.placeholderText || 'Taip mesej anda...';
  config.avatarUrl = config.avatarUrl || '';
  config.position = config.position || 'bottom-right';
  config.showBranding = config.showBranding !== false;
  
  var sessionId = '';
  var widgetTicket = '';
  var ticketExpiresAt = 0;
  var ticketPromise = null;
  var isOpen = false;
  var isLoading = false;
  
  // CSS
  var css = document.createElement('style');
  css.textContent = '#chatme-root{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;font-size:14px;line-height:1.5}#chatme-root *{box-sizing:border-box;margin:0;padding:0}#chatme-bubble{position:fixed;z-index:999999;width:60px;height:60px;border-radius:50%;cursor:pointer;box-shadow:0 4px 20px rgba(0,0,0,0.2);transition:transform 0.2s,box-shadow 0.2s;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#fff}#chatme-bubble:hover{transform:scale(1.08);box-shadow:0 6px 28px rgba(0,0,0,0.3)}#chatme-bubble.bottom-right{bottom:20px;right:12px}#chatme-bubble.bottom-left{bottom:20px;left:12px}#chatme-bubble img{width:100%;height:100%;object-fit:cover}#chatme-window{display:none;position:fixed;z-index:999998;width:min(370px,calc(100% - 24px));height:min(520px,calc(100vh - 100px));border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,0.15);overflow:hidden;flex-direction:column;background:#fff}#chatme-window.open{display:flex}#chatme-window.bottom-right{bottom:90px;right:12px}#chatme-window.bottom-left{bottom:90px;left:12px}#chatme-header{padding:14px 16px;display:flex;align-items:center;gap:10px;color:#fff;flex-shrink:0}#chatme-header img{width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.3)}#chatme-header-info{flex:1;min-width:0}#chatme-header-info .name{font-weight:600;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}#chatme-header-info .status{font-size:11px;opacity:0.8}#chatme-close{background:none;border:none;color:#fff;cursor:pointer;font-size:24px;padding:2px 8px;border-radius:6px;line-height:1;opacity:0.8}#chatme-close:hover{opacity:1;background:rgba(255,255,255,0.15)}#chatme-messages{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:8px;background:#f9fafb}#chatme-root .chatme-msg{max-width:85%;padding:10px 14px;border-radius:14px;line-height:1.45;font-size:13.5px;animation:fadeIn 0.3s ease;word-wrap:break-word}@keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}.chatme-msg.bot{align-self:flex-start;background:#fff;color:#1f2937;border:1px solid #e5e7eb;border-bottom-left-radius:4px}.chatme-msg.user{align-self:flex-end;color:#fff;border-bottom-right-radius:4px}#chatme-typing{display:none;align-self:flex-start;padding:10px 14px;background:#fff;border-radius:14px;border:1px solid #e5e7eb;border-bottom-left-radius:4px}#chatme-typing.show{display:block}#chatme-typing span{display:inline-block;width:7px;height:7px;border-radius:50%;background:#9ca3af;animation:bounce 1.4s infinite ease-in-out both;margin:0 2px}#chatme-typing span:nth-child(1){animation-delay:-0.32s}#chatme-typing span:nth-child(2){animation-delay:-0.16s}@keyframes bounce{0%,80%,100%{transform:scale(0)}40%{transform:scale(1)}}#chatme-input-area{padding:12px 14px;border-top:1px solid #e5e7eb;display:flex;gap:8px;background:#fff;flex-shrink:0}#chatme-input{flex:1;min-width:0;border:1px solid #d1d5db;border-radius:24px;padding:10px 16px;font-size:13.5px;outline:none;background:#f9fafb;transition:border-color 0.2s}#chatme-input:focus{border-color:#6366f1;background:#fff}#chatme-send{width:38px;height:38px;border-radius:50%;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;flex-shrink:0;transition:transform 0.15s}#chatme-send:hover{transform:scale(1.05)}#chatme-send:active{transform:scale(0.95)}#chatme-brand{text-align:center;padding:6px;font-size:10px;color:#67655f;background:#f9fafb;border-top:1px solid #e5e7eb;flex-shrink:0}#chatme-brand a{color:#67655f;text-decoration:none}';
  css.textContent += '@media(max-width:640px){#chatme-input{font-size:16px}#chatme-window{height:min(520px,calc(100dvh - 100px))}}';
  document.head.appendChild(css);
  
  // Build DOM
  var root = document.createElement('div');
  root.id = 'chatme-root';
  root.innerHTML = '<button id="chatme-bubble" type="button" aria-label="Buka ruang sembang" aria-controls="chatme-window" aria-expanded="false"><img alt=""></button>' +
    '<div id="chatme-window" role="dialog" aria-label="Ruang sembang" aria-hidden="true">' +
    '<div id="chatme-header"><img alt=""><div id="chatme-header-info"><div class="name"></div><div class="status">Sedia membantu</div></div><button id="chatme-close" type="button" aria-label="Tutup ruang sembang">&times;</button></div>' +
    '<div id="chatme-messages" role="log" aria-live="polite" aria-relevant="additions"></div>' +
    '<div id="chatme-typing"><span></span><span></span><span></span></div>' +
    '<div id="chatme-input-area"><input id="chatme-input" type="text" autocomplete="off" aria-label="Mesej"><button id="chatme-send" type="button" aria-label="Hantar mesej">↑</button></div>' +
    '<div id="chatme-brand">Disediakan oleh <a href="https://chatme.akmalmarvis.com" target="_blank" rel="noopener noreferrer">ChatMe</a></div>' +
    '</div>';
  document.body.appendChild(root);

  // Elements
  var bubble = document.getElementById('chatme-bubble');
  var windowEl = document.getElementById('chatme-window');
  var messagesEl = document.getElementById('chatme-messages');
  var typingEl = document.getElementById('chatme-typing');
  var inputEl = document.getElementById('chatme-input');
  var sendBtn = document.getElementById('chatme-send');
  var closeBtn = document.getElementById('chatme-close');
  var brandEl = document.getElementById('chatme-brand');

  bubble.className = config.position;
  windowEl.className = config.position;
  bubble.querySelector('img').src = config.avatarUrl;
  document.querySelector('#chatme-header img').src = config.avatarUrl;
  document.querySelector('#chatme-header-info .name').textContent = config.botName;
  inputEl.placeholder = config.placeholderText;
  brandEl.hidden = !config.showBranding;

  function readableTextColor(hex) {
    var match = /^#([0-9a-f]{6})$/i.exec(hex || '');
    if (!match) return '#ffffff';

    var value = parseInt(match[1], 16);
    var channels = [(value >> 16) & 255, (value >> 8) & 255, value & 255].map(function(channel) {
      var normalized = channel / 255;
      return normalized <= 0.04045 ? normalized / 12.92 : Math.pow((normalized + 0.055) / 1.055, 2.4);
    });
    var luminance = (0.2126 * channels[0]) + (0.7152 * channels[1]) + (0.0722 * channels[2]);

    return (1.05 / (luminance + 0.05)) >= ((luminance + 0.05) / 0.05)
      ? '#ffffff'
      : '#111827';
  }

  // Set header color
  var primaryTextColor = readableTextColor(config.primaryColor);
  document.getElementById('chatme-header').style.background = config.primaryColor;
  document.getElementById('chatme-header').style.color = primaryTextColor;
  sendBtn.style.background = config.primaryColor;
  sendBtn.style.color = primaryTextColor;
  closeBtn.style.color = primaryTextColor;
  
  function addMsg(text, role) {
    var div = document.createElement('div');
    div.className = 'chatme-msg ' + role;
    if (role === 'user') {
      div.style.background = config.primaryColor;
      div.style.color = primaryTextColor;
    }
    div.textContent = text;
    messagesEl.appendChild(div);
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }
  
  function showTyping() {
    isLoading = true;
    typingEl.className = 'show';
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }
  
  function hideTyping() {
    isLoading = false;
    typingEl.className = '';
  }

  function refreshTicket() {
    if (ticketPromise) return ticketPromise;

    ticketPromise = fetch(config.apiUrl + '/config', {
      method: 'GET',
      headers: {'Accept': 'application/json'},
      credentials: 'omit',
      cache: 'no-store'
    })
    .then(function(response) {
      if (!response.ok) throw new Error('ChatMe bootstrap failed');
      return response.json();
    })
    .then(function(payload) {
      if (!payload.widget_ticket || !payload.widget_session_id || !payload.ticket_expires_at) {
        throw new Error('ChatMe bootstrap response was invalid');
      }

      widgetTicket = payload.widget_ticket;
      sessionId = payload.widget_session_id;
      ticketExpiresAt = Date.parse(payload.ticket_expires_at) || 0;
    })
    .finally(function() {
      ticketPromise = null;
    });

    return ticketPromise;
  }

  function ensureTicket() {
    if (widgetTicket && sessionId && ticketExpiresAt > Date.now() + 30000) {
      return Promise.resolve();
    }

    return refreshTicket();
  }

  function performChat(text, canRetryTicket, signal) {
    return ensureTicket()
      .then(function() {
        var options = {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
          credentials: 'omit',
          body: JSON.stringify({
            message: text,
            session_id: sessionId,
            widget_ticket: widgetTicket
          })
        };
        if (signal) options.signal = signal;

        return fetch(config.apiUrl + '/chat', options);
      })
      .then(function(response) {
        if (response.status === 401 && canRetryTicket) {
          widgetTicket = '';
          sessionId = '';
          ticketExpiresAt = 0;

          return refreshTicket().then(function() {
            return performChat(text, false, signal);
          });
        }
        if (!response.ok) throw new Error('ChatMe request failed');

        return response.json();
      });
  }
  
  function send() {
    var text = inputEl.value.trim();
    if (!text || isLoading) return;
    addMsg(text, 'user');
    inputEl.value = '';
    showTyping();
    
    var controller = typeof AbortController === 'function' ? new AbortController() : null;
    var timeoutId;
    var timeout = new Promise(function(_, reject) {
      timeoutId = setTimeout(function() {
        if (controller) controller.abort();
        reject(new Error('ChatMe request timed out'));
      }, 15000);
    });

    Promise.race([performChat(text, true, controller ? controller.signal : null), timeout])
    .then(function(d) {
      if (!d.response) throw new Error('ChatMe response was empty');
      hideTyping();
      addMsg(d.response, 'bot');
      if (d.session_id) sessionId = d.session_id;
    })
    .catch(function() {
      hideTyping();
      if (!inputEl.value.trim()) inputEl.value = text;
      addMsg('Maaf, mesej tidak dapat dihantar. Sila cuba lagi.', 'bot');
      inputEl.focus();
    })
    .finally(function() {
      clearTimeout(timeoutId);
    });
  }
  
  function toggle() {
    isOpen = !isOpen;
    windowEl.className = isOpen ? 'open ' + config.position : config.position;
    windowEl.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    bubble.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    bubble.style.display = isOpen ? 'none' : 'flex';
    if (isOpen && messagesEl.children.length === 0) {
      addMsg(config.welcomeMessage, 'bot');
    }
    if (isOpen) {
      setTimeout(function() { inputEl.focus(); }, 300);
    } else {
      bubble.focus();
    }
  }
  
  bubble.addEventListener('click', toggle);
  closeBtn.addEventListener('click', toggle);
  sendBtn.addEventListener('click', send);
  inputEl.addEventListener('keydown', function(e) { if (e.key === 'Enter') send(); });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && isOpen) toggle();
  });

  refreshTicket().catch(function() {
    widgetTicket = '';
    sessionId = '';
    ticketExpiresAt = 0;
  });
  
})();
