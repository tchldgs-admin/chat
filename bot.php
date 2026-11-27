<?php
// 外部サイトへのiframe埋め込みを許可
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

$site_id = trim($_GET['site'] ?? '');
$initial_open = !empty($_GET['open']);
$is_preview = !empty($_GET['preview']); // 管理画面プレビュー用フラグ
if ($site_id === '') {
    http_response_code(404);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>サポートチャット</title>
<style>
    :root {
        --theme-color: #28a745;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
        width: 100%; height: 100%;
        background: transparent !important;
        font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", "Meiryo", sans-serif;
        font-size: 13px; color: #2d3748;
        overflow: hidden;
    }

    .widget-root {
        width: 100%; height: 100%;
        display: flex; flex-direction: column; justify-content: flex-end; align-items: flex-end;
        padding: 8px;
    }

    /* --------------------------------------------------
       折りたたみ時UI（アイキャッチャー画像 ＋ 下部起動バー）
       -------------------------------------------------- */
    .launcher-container {
        display: flex; flex-direction: column; align-items: flex-end;
        max-width: 100%;
        animation: fadeInUp 0.25s ease;
    }
    .launcher-container.hidden { display: none; }

    .eyecatcher-wrap {
        position: relative;
        margin-bottom: 8px;
        cursor: pointer;
        transition: transform 0.18s ease;
    }
    .eyecatcher-wrap:hover { transform: translateY(-2px); }
    .eyecatcher-close {
        position: absolute; top: -8px; right: -4px;
        width: 22px; height: 22px; border-radius: 50%;
        background: rgba(45, 55, 72, 0.85); color: #fff;
        border: 1.5px solid #fff; font-size: 13px; line-height: 1;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; z-index: 5; box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    .eyecatcher-close:hover { background: #1a202c; }
    .eyecatcher-img {
        max-width: 210px; max-height: 150px;
        display: block; border-radius: 10px;
        filter: drop-shadow(0 4px 10px rgba(0,0,0,0.16));
    }

    .default-eyecatcher {
        background: #ffffff;
        border: 2px solid var(--theme-color);
        border-radius: 14px;
        padding: 10px 14px;
        box-shadow: 0 6px 16px rgba(0,0,0,0.14);
        display: flex; align-items: center; gap: 10px;
        position: relative;
    }
    .default-eyecatcher::after {
        content: ""; position: absolute; bottom: -8px; right: 28px;
        border-width: 8px 7px 0; border-style: solid;
        border-color: var(--theme-color) transparent transparent transparent;
    }
    .ec-phone-icon {
        width: 42px; height: 52px; border-radius: 8px;
        background: var(--theme-color); color: #fff;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        font-weight: bold; font-size: 10px; flex-shrink: 0;
        box-shadow: inset 0 0 0 2px rgba(255,255,255,0.35);
    }
    .ec-text-box { line-height: 1.35; }
    .ec-badge {
        display: inline-block; background: var(--theme-color); color: #fff;
        font-size: 10px; font-weight: bold; padding: 1px 6px; border-radius: 3px; margin-bottom: 3px;
    }
    .ec-main-txt { font-size: 12px; font-weight: bold; color: #2d3748; }

    .launcher-bar {
        background: var(--theme-color);
        color: #ffffff;
        border: none;
        border-radius: 6px;
        padding: 12px 22px;
        min-width: 260px;
        font-size: 13.5px;
        font-weight: bold;
        letter-spacing: 0.3px;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(0,0,0,0.22);
        display: flex; align-items: center; justify-content: center; gap: 8px;
        transition: filter 0.15s, transform 0.15s;
    }
    .launcher-bar:hover { filter: brightness(1.06); transform: translateY(-1px); }

    .launcher-circle {
        width: 60px; height: 60px; border-radius: 50%;
        background: var(--theme-color); color: #fff; border: none;
        box-shadow: 0 4px 14px rgba(0,0,0,0.24);
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; transition: transform 0.15s;
    }
    .launcher-circle:hover { transform: scale(1.05); }

    /* --------------------------------------------------
       展開時チャットウィンドウ
       -------------------------------------------------- */
    .chat-window {
        width: 100%; height: 100%;
        max-width: 370px; max-height: 560px;
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 8px 28px rgba(0,0,0,0.22);
        border: 1px solid #e2e8f0;
        display: flex; flex-direction: column;
        overflow: hidden;
        animation: fadeInUp 0.2s ease;
    }
    .chat-window.hidden { display: none; }

    .chat-header {
        background: var(--theme-color);
        color: #ffffff;
        padding: 12px 14px;
        display: flex; align-items: center; justify-content: space-between;
        flex-shrink: 0;
    }
    .header-profile { display: flex; align-items: center; gap: 10px; overflow: hidden; }
    .header-avatar {
        width: 36px; height: 36px; border-radius: 50%;
        background: rgba(255,255,255,0.22); color: #fff;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .header-titles { overflow: hidden; }
    .header-title { font-size: 14px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .header-sub { font-size: 11px; opacity: 0.9; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .header-actions { display: flex; align-items: center; gap: 6px; }
    .btn-header-icon {
        background: rgba(255,255,255,0.18); border: none; color: #fff;
        width: 28px; height: 28px; border-radius: 50%; cursor: pointer;
        display: flex; align-items: center; justify-content: center; font-size: 15px;
    }
    .btn-header-icon:hover { background: rgba(255,255,255,0.32); }

    .preview-notice-strip {
        background: #fefcbf; color: #744210; font-size: 11px; font-weight: bold;
        padding: 4px 10px; text-align: center; border-bottom: 1px solid #f6e05e;
        flex-shrink: 0;
    }

    .chat-messages {
        flex: 1;
        background: #f4f7f9;
        padding: 14px 12px;
        overflow-y: auto;
        display: flex; flex-direction: column; gap: 12px;
    }

    .msg-row { display: flex; gap: 8px; align-items: flex-start; max-width: 92%; }
    .msg-row.bot { align-self: flex-start; }
    .msg-row.user { align-self: flex-end; flex-direction: row-reverse; }

    .bot-mini-avatar {
        width: 30px; height: 30px; border-radius: 50%;
        background: var(--theme-color); color: #fff;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;
    }
    .msg-bubble {
        padding: 10px 13px;
        border-radius: 14px;
        line-height: 1.55;
        font-size: 13px;
        white-space: pre-wrap;
        word-break: break-word;
        box-shadow: 0 1px 2px rgba(0,0,0,0.06);
    }
    .msg-row.bot .msg-bubble {
        background: #ffffff; color: #2d3748;
        border-top-left-radius: 3px; border: 1px solid #e2e8f0;
    }
    .msg-row.user .msg-bubble {
        background: var(--theme-color); color: #ffffff;
        border-top-right-radius: 3px;
    }

    .kb-cat-tag {
        display: inline-block; background: #edf2f7; color: #4a5568;
        font-size: 10px; font-weight: bold; padding: 1px 6px; border-radius: 3px;
        margin-bottom: 4px;
    }

    .options-list {
        display: flex; flex-direction: column; gap: 6px;
        margin-top: 4px; padding-left: 38px; max-width: 94%;
    }
    .option-btn {
        background: #ffffff;
        border: 1.5px solid var(--theme-color);
        color: var(--theme-color);
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: bold;
        text-align: left;
        cursor: pointer;
        transition: all 0.15s;
        display: flex; align-items: center; justify-content: space-between; gap: 6px;
        text-decoration: none;
    }
    .option-btn:hover:not(:disabled) {
        background: var(--theme-color);
        color: #ffffff;
    }
    .option-btn:disabled {
        opacity: 0.55; cursor: default;
    }

    .inline-ticket-form {
        background: #ffffff; border: 1px solid #cbd5e0; border-radius: 10px;
        padding: 12px; margin-left: 38px; max-width: 88%;
        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }
    .itf-group { margin-bottom: 8px; }
    .itf-group label { display: block; font-size: 11px; font-weight: bold; color: #4a5568; margin-bottom: 3px; }
    .itf-input {
        width: 100%; padding: 7px 9px; border: 1px solid #cbd5e0; border-radius: 5px;
        font-size: 12.5px; font-family: inherit;
    }
    .itf-input:focus { outline: none; border-color: var(--theme-color); }
    .itf-submit {
        width: 100%; background: var(--theme-color); color: #fff; border: none;
        padding: 9px; border-radius: 6px; font-weight: bold; font-size: 13px; cursor: pointer;
        margin-top: 4px;
    }
    .itf-submit:disabled { opacity: 0.6; cursor: not-allowed; }

    .maint-box {
        background: #fffaf0; border: 1px solid #fbd38d; color: #9c4221;
        padding: 16px; border-radius: 8px; text-align: center; line-height: 1.65; font-size: 12.5px;
    }
    .maint-box strong { display: block; font-size: 14px; margin-bottom: 6px; color: #7b341e; }

    .chat-footer {
        background: #ffffff;
        border-top: 1px solid #e2e8f0;
        padding: 8px 10px;
        display: flex; align-items: center; gap: 6px;
        flex-shrink: 0;
    }
    .chat-footer.hidden { display: none; }
    .chat-input {
        flex: 1; border: 1px solid #cbd5e0; border-radius: 18px;
        padding: 8px 14px; font-size: 13px; outline: none;
    }
    .chat-input:focus { border-color: var(--theme-color); }
    .btn-send-msg {
        background: var(--theme-color); color: #fff; border: none;
        width: 34px; height: 34px; border-radius: 50%; cursor: pointer;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .btn-send-msg:hover { filter: brightness(1.06); }

    .typing-dots { display: inline-flex; gap: 4px; padding: 4px 2px; }
    .typing-dots span {
        width: 6px; height: 6px; border-radius: 50%; background: #a0aec0;
        animation: bounce 1.2s infinite ease-in-out;
    }
    .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
    .typing-dots span:nth-child(3) { animation-delay: 0.4s; }

    @keyframes bounce {
        0%, 80%, 100% { transform: scale(0.7); opacity: 0.5; }
        40% { transform: scale(1.1); opacity: 1; }
    }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
</head>
<body>
<div class="widget-root" id="widget-root" style="display:none;">
    <div class="launcher-container" id="launcher-container">
        <div class="eyecatcher-wrap" id="eyecatcher-wrap" style="display:none;">
            <button type="button" class="eyecatcher-close" id="eyecatcher-close" title="閉じる">×</button>
            <div id="eyecatcher-content"></div>
        </div>
        <button type="button" class="launcher-bar" id="launcher-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
            <span id="launcher-btn-text">お問い合わせはこちらをクリック</span>
        </button>
    </div>

    <div class="chat-window hidden" id="chat-window">
        <div class="chat-header">
            <div class="header-profile">
                <div class="header-avatar" id="header-avatar"></div>
                <div class="header-titles">
                    <div class="header-title" id="header-title">サポートチャット</div>
                    <div class="header-sub" id="header-subtitle">ご質問をお選びください</div>
                </div>
            </div>
            <div class="header-actions">
                <button type="button" class="btn-header-icon" id="btn-reset-chat" title="最初からやり直す">↻</button>
                <button type="button" class="btn-header-icon" id="btn-minimize-chat" title="最小化する">－</button>
            </div>
        </div>

        <?php if ($is_preview): ?>
        <div class="preview-notice-strip">テストモード中（会話履歴・チケットは保存されません）</div>
        <?php endif; ?>

        <div class="chat-messages" id="chat-messages"></div>

        <form class="chat-footer" id="chat-input-form" autocomplete="off">
            <input type="text" class="chat-input" id="chat-text-input" placeholder="質問キーワードやメッセージを入力...">
            <button type="submit" class="btn-send-msg" title="送信">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    const siteId      = <?php echo json_encode($site_id); ?>;
    const initialOpen = <?php echo json_encode($initial_open); ?>;
    const isPreview   = <?php echo json_encode($is_preview); ?>;
    const sessionKey  = 'sess_' + Math.random().toString(36).slice(2) + Date.now().toString(36);

    let botSetting = null;
    let isMaintenance = false;
    let isOffHours = false;
    let conversationLog = [];

    const avatarSvgs = {
        operator: '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h3c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z"/></svg>',
        robot: '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 9V7c0-1.1-.9-2-2-2h-3c0-1.66-1.34-3-3-3S9 3.34 9 5H6c-1.1 0-2 .9-2 2v2c-1.66 0-3 1.34-3 3s1.34 3 3 3v4c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-4c1.66 0 3-1.34 3-3s-1.34-3-3-3zM7.5 11.5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5S9.83 13 9 13s-1.5-.67-1.5-1.5zM16 17H8v-2h8v2zm-1-4c-.83 0-1.5-.67-1.5-1.5S14.17 10 15 10s1.5.67 1.5 1.5S15.83 13 15 13z"/></svg>',
        chat: '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>'
    };

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    function notifyParentResize(isOpen) {
        try {
            window.parent.postMessage({
                type: 'tc_plus_bot_resize',
                site: siteId,
                open: isOpen
            }, '*');
        } catch (e) {}
    }

    function openChatWindow() {
        document.getElementById('launcher-container').classList.add('hidden');
        document.getElementById('chat-window').classList.remove('hidden');
        notifyParentResize(true);
        if (conversationLog.length === 0) {
            startConversation();
        }
    }

    function closeChatWindow() {
        document.getElementById('chat-window').classList.add('hidden');
        document.getElementById('launcher-container').classList.remove('hidden');
        notifyParentResize(false);
    }

    async function syncLog() {
        // プレビュー時はデータベース（履歴・訪問者・レポート）へ一切記録しない
        if (isPreview) return;
        try {
            const fd = new FormData();
            fd.append('action', 'save_log');
            fd.append('site_id', siteId);
            fd.append('session_key', sessionKey);
            fd.append('messages_json', JSON.stringify(conversationLog));
            await fetch('bot_api.php', { method: 'POST', body: fd });
        } catch (e) {}
    }

    function scrollToBottom() {
        const box = document.getElementById('chat-messages');
        box.scrollTop = box.scrollHeight;
    }

    function appendBotBubble(text, callback, categoryLabel = '') {
        const box = document.getElementById('chat-messages');
        const iconSvg = avatarSvgs[botSetting.bot_icon_type] || avatarSvgs.operator;

        const typingEl = document.createElement('div');
        typingEl.className = 'msg-row bot';
        typingEl.innerHTML = `<div class="bot-mini-avatar">${iconSvg}</div><div class="msg-bubble"><div class="typing-dots"><span></span><span></span><span></span></div></div>`;
        box.appendChild(typingEl);
        scrollToBottom();

        setTimeout(() => {
            const bubble = typingEl.querySelector('.msg-bubble');
            if (categoryLabel) {
                bubble.innerHTML = `<div class="kb-cat-tag">${esc(categoryLabel)}</div><div>${esc(text)}</div>`;
            } else {
                bubble.textContent = text;
            }
            conversationLog.push({ sender: 'bot', text: text, time: new Date().toISOString() });
            syncLog();
            scrollToBottom();
            if (typeof callback === 'function') callback();
        }, 380);
    }

    function appendUserBubble(text) {
        const box = document.getElementById('chat-messages');
        const el = document.createElement('div');
        el.className = 'msg-row user';
        el.innerHTML = `<div class="msg-bubble">${esc(text)}</div>`;
        box.appendChild(el);
        conversationLog.push({ sender: 'user', text: text, time: new Date().toISOString() });
        syncLog();
        scrollToBottom();
    }

    function disablePreviousOptions() {
        document.querySelectorAll('.options-list .option-btn').forEach(btn => {
            if (btn.tagName === 'BUTTON') btn.disabled = true;
        });
    }

    function appendFollowupMenu() {
        const box = document.getElementById('chat-messages');
        const tree = botSetting.tree || [];
        const formNode = tree.find(n => n.action_type === 'form');
        const optWrap = document.createElement('div');
        optWrap.className = 'options-list';

        if (formNode) {
            const btnForm = document.createElement('button');
            btnForm.type = 'button';
            btnForm.className = 'option-btn';
            btnForm.innerHTML = '<span>解決しないので問い合わせる</span><span>›</span>';
            btnForm.addEventListener('click', () => {
                disablePreviousOptions();
                appendUserBubble('解決しないので問い合わせる');
                renderNode(formNode.id);
            });
            optWrap.appendChild(btnForm);
        }

        const btnTop = document.createElement('button');
        btnTop.type = 'button';
        btnTop.className = 'option-btn';
        btnTop.innerHTML = '<span>最初のメニューに戻る</span><span>›</span>';
        btnTop.addEventListener('click', () => {
            disablePreviousOptions();
            appendUserBubble('最初のメニューに戻る');
            renderNode('node_start');
        });
        optWrap.appendChild(btnTop);

        box.appendChild(optWrap);
        scrollToBottom();
    }

    function renderNode(nodeId) {
        const tree = botSetting.tree || [];
        const node = tree.find(n => n.id === nodeId) || tree[0];
        if (!node) return;

        appendBotBubble(node.message, () => {
            const box = document.getElementById('chat-messages');

            if (node.link_url && node.link_label) {
                const linkWrap = document.createElement('div');
                linkWrap.className = 'options-list';
                linkWrap.innerHTML = `<a href="${esc(node.link_url)}" target="_blank" rel="noopener" class="option-btn"><span>${esc(node.link_label)}</span><span>↗</span></a>`;
                box.appendChild(linkWrap);
            }

            if (Array.isArray(node.options) && node.options.length > 0) {
                const optWrap = document.createElement('div');
                optWrap.className = 'options-list';
                node.options.forEach(opt => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'option-btn';
                    btn.innerHTML = `<span>${esc(opt.label)}</span><span>›</span>`;
                    btn.addEventListener('click', () => {
                        disablePreviousOptions();
                        appendUserBubble(opt.label);
                        if (opt.next_node_id) {
                            renderNode(opt.next_node_id);
                        }
                    });
                    optWrap.appendChild(btn);
                });
                box.appendChild(optWrap);
            }

            if (node.action_type === 'form') {
                renderInlineTicketForm();
            }

            scrollToBottom();
        });
    }

    function renderInlineTicketForm() {
        const box = document.getElementById('chat-messages');
        const formCard = document.createElement('div');
        formCard.className = 'inline-ticket-form';
        formCard.innerHTML = `
            <div class="itf-group"><label>お名前 *</label><input type="text" class="itf-input" name="cname" required placeholder="例：山田 太郎"></div>
            <div class="itf-group"><label>メールアドレス *</label><input type="email" class="itf-input" name="cemail" required placeholder="例：taro@example.com"></div>
            <div class="itf-group"><label>件名 *</label><input type="text" class="itf-input" name="csubject" required value="チャットボットからのお問い合わせ"></div>
            <div class="itf-group"><label>お問い合わせ内容 *</label><textarea class="itf-input" name="cbody" rows="3" required placeholder="ご用件をご入力ください"></textarea></div>
            <button type="button" class="itf-submit">${isPreview ? '送信テストを実行する' : 'この内容で送信する'}</button>
        `;

        const submitBtn = formCard.querySelector('.itf-submit');
        submitBtn.addEventListener('click', async () => {
            const cname    = formCard.querySelector('[name="cname"]').value.trim();
            const cemail   = formCard.querySelector('[name="cemail"]').value.trim();
            const csubject = formCard.querySelector('[name="csubject"]').value.trim();
            const cbody    = formCard.querySelector('[name="cbody"]').value.trim();

            if (!cname || !cemail || !cbody) {
                alert('お名前・メールアドレス・お問い合わせ内容を入力してください。');
                return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(cemail)) {
                alert('正しいメールアドレスを入力してください。');
                return;
            }

            // プレビュー時はチケット起票・メール送信を行わずテスト完了メッセージを表示
            if (isPreview) {
                formCard.remove();
                appendUserBubble(`【フォーム送信テスト】\nお名前：${cname}\n内容：${cbody}`);
                appendBotBubble(`【テストプレビュー完了】\n実際の本番画面ではこの内容でチケット管理へ自動登録され、${cemail} 宛に受付完了メールが送信されます。\n（※テストモードのため、データベースへの起票・メール送信は行われていません）`, () => {
                    appendFollowupMenu();
                });
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = '送信中...';

            const historyText = conversationLog.map(m => (m.sender === 'bot' ? '【ボット】' : '【お客様】') + m.text).join('\n');

            try {
                const fd = new FormData();
                fd.append('action', 'submit_ticket');
                fd.append('site_id', siteId);
                fd.append('session_key', sessionKey);
                fd.append('customer_name', cname);
                fd.append('customer_email', cemail);
                fd.append('subject', csubject || 'チャットボットからのお問い合わせ');
                fd.append('body', cbody);
                fd.append('chat_history', historyText);

                const res = await fetch('bot_api.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    formCard.remove();
                    appendUserBubble(`【フォーム送信】\nお名前：${cname}\n内容：${cbody}`);
                    appendBotBubble(`お問い合わせを受け付けました（チケットID: #${data.ticket_id}）。\nご入力いただいたメールアドレス宛に受付完了メールをお送りしましたのでご確認ください。`);
                } else {
                    alert(data.error || '送信に失敗しました。');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'この内容で送信する';
                }
            } catch (err) {
                alert('通信エラーが発生しました。');
                submitBtn.disabled = false;
                submitBtn.textContent = 'この内容で送信する';
            }
        });

        box.appendChild(formCard);
        scrollToBottom();
    }

    function startConversation() {
        const box = document.getElementById('chat-messages');
        box.innerHTML = '';
        conversationLog = [];

        // 本番表示時のみメンテナンスモード案内を表示（プレビュー時はテスト操作を優先）
        if (isMaintenance && !isPreview) {
            document.getElementById('chat-input-form').classList.add('hidden');
            box.innerHTML = `
                <div class="maint-box">
                    <strong>現在メンテナンス中です</strong>
                    ただいまシステムメンテナンスを実施しているため、チャットボットおよびお問い合わせ受付を一時休止しております。<br>
                    ご不便をおかけいたしますが、メンテナンス終了後に改めてご利用ください。
                </div>`;
            return;
        }

        if (isOffHours && botSetting.off_hours_message && !isPreview) {
            appendBotBubble(botSetting.off_hours_message, () => {
                renderNode('node_start');
            });
        } else {
            renderNode('node_start');
        }
    }

    async function queryKnowledgeBase(queryText, kbId = 0) {
        const fd = new FormData();
        fd.append('action', 'ask_knowledge');
        fd.append('site_id', siteId);
        if (isPreview) fd.append('preview', '1');
        if (queryText) fd.append('query', queryText);
        if (kbId > 0) fd.append('kb_id', String(kbId));

        try {
            const res = await fetch('bot_api.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success && data.mode === 'direct' && data.match) {
                appendBotBubble(data.match.answer, () => {
                    appendFollowupMenu();
                }, data.match.category || 'Q&A回答');
                return;
            }

            if (data.success && data.mode === 'candidates' && Array.isArray(data.matches) && data.matches.length > 0) {
                appendBotBubble('近い内容のよくある質問が見つかりました。該当する項目をお選びください。', () => {
                    const box = document.getElementById('chat-messages');
                    const optWrap = document.createElement('div');
                    optWrap.className = 'options-list';
                    data.matches.forEach(m => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'option-btn';
                        btn.innerHTML = `<span>${esc(m.question)}</span><span>›</span>`;
                        btn.addEventListener('click', () => {
                            disablePreviousOptions();
                            appendUserBubble(m.question);
                            queryKnowledgeBase('', m.id);
                        });
                        optWrap.appendChild(btn);
                    });
                    box.appendChild(optWrap);
                    scrollToBottom();
                });
                return;
            }
        } catch (e) {
            console.error(e);
        }

        const fb = botSetting.fallback_message || 'ご質問ありがとうございます。下のメニューからお選びいただくか、お問い合わせフォームをご利用ください。';
        appendBotBubble(fb, () => {
            const tree = botSetting.tree || [];
            const startNode = tree.find(n => n.id === 'node_start') || tree[0];
            if (startNode && Array.isArray(startNode.options)) {
                const box = document.getElementById('chat-messages');
                const optWrap = document.createElement('div');
                optWrap.className = 'options-list';
                startNode.options.forEach(opt => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'option-btn';
                    btn.innerHTML = `<span>${esc(opt.label)}</span><span>›</span>`;
                    btn.addEventListener('click', () => {
                        disablePreviousOptions();
                        appendUserBubble(opt.label);
                        renderNode(opt.next_node_id);
                    });
                    optWrap.appendChild(btn);
                });
                box.appendChild(optWrap);
                scrollToBottom();
            }
        });
    }

    try {
        const res = await fetch('bot_api.php?action=public_config&site=' + encodeURIComponent(siteId), { cache: 'no-store' });
        const data = await res.json();
        if (!data.success || !data.setting) {
            return;
        }
        // 本番表示時のみ「enabled=0」で非表示にする（プレビュー時は無効でもテスト操作可能）
        if (!data.setting.enabled && !isPreview) {
            return;
        }

        botSetting = data.setting;
        isMaintenance = !!data.maintenance_mode;
        isOffHours = !!data.is_off_hours;

        document.documentElement.style.setProperty('--theme-color', botSetting.theme_color || '#28a745');
        document.getElementById('header-title').textContent = botSetting.header_title || 'サポートチャット';
        document.getElementById('header-subtitle').textContent = botSetting.header_subtitle || '';
        document.getElementById('header-avatar').innerHTML = avatarSvgs[botSetting.bot_icon_type] || avatarSvgs.operator;

        const launcherBtn = document.getElementById('launcher-btn');
        if (botSetting.launcher_type === 'circle') {
            launcherBtn.className = 'launcher-circle';
            launcherBtn.innerHTML = '<svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>';
        } else {
            launcherBtn.className = 'launcher-bar';
            document.getElementById('launcher-btn-text').textContent = botSetting.launcher_text || 'お問い合わせはこちらをクリック';
        }

        if (botSetting.eyecatcher_enabled && (!isMaintenance || isPreview)) {
            const ecWrap = document.getElementById('eyecatcher-wrap');
            const ecContent = document.getElementById('eyecatcher-content');
            if (botSetting.eyecatcher_url) {
                ecContent.innerHTML = `<img src="${esc(botSetting.eyecatcher_url)}" alt="ご案内" class="eyecatcher-img">`;
            } else {
                ecContent.innerHTML = `
                    <div class="default-eyecatcher">
                        <div class="ec-phone-icon"><span>CHAT</span><span>BOT</span></div>
                        <div class="ec-text-box">
                            <span class="ec-badge">24時間自動案内</span>
                            <div class="ec-main-txt">ご質問・ご相談は<br>こちらからどうぞ！</div>
                        </div>
                    </div>`;
            }
            ecWrap.style.display = 'block';
        }

        if (!botSetting.allow_free_input) {
            document.getElementById('chat-input-form').classList.add('hidden');
        }

        document.getElementById('widget-root').style.display = 'flex';

        if (initialOpen) {
            openChatWindow();
        } else if (botSetting.auto_open_seconds > 0 && !isMaintenance && !isPreview) {
            setTimeout(() => {
                if (!document.getElementById('launcher-container').classList.contains('hidden')) {
                    openChatWindow();
                }
            }, botSetting.auto_open_seconds * 1000);
        }

    } catch (e) {
        console.error(e);
    }

    document.getElementById('launcher-btn').addEventListener('click', openChatWindow);
    document.getElementById('eyecatcher-content').addEventListener('click', openChatWindow);
    document.getElementById('eyecatcher-close').addEventListener('click', function(e) {
        e.stopPropagation();
        document.getElementById('eyecatcher-wrap').style.display = 'none';
    });
    document.getElementById('btn-minimize-chat').addEventListener('click', closeChatWindow);
    document.getElementById('btn-reset-chat').addEventListener('click', startConversation);

    document.getElementById('chat-input-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const input = document.getElementById('chat-text-input');
        const text = input.value.trim();
        if (!text) return;

        input.value = '';
        disablePreviousOptions();
        appendUserBubble(text);
        queryKnowledgeBase(text, 0);
    });
});
</script>
</body>
</html>