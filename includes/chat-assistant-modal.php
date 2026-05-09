<!-- Chat Widget FAB -->
<div id="chat-fab" class="fixed bottom-6 left-6 z-[1000] transition-all duration-300 transform hover:scale-110 active:scale-95">
    <button onclick="toggleChat()" class="w-14 h-14 bg-[#4E3629] rounded-full shadow-2xl flex items-center justify-center text-white border-2 border-white/20">
        <i class="fas fa-robot text-2xl"></i>
    </button>
</div>

<!-- Chat Modal -->
<div id="chat-modal" class="fixed inset-0 z-[2000] hidden opacity-0 transition-all duration-300 bg-black/40 backdrop-blur-sm sm:inset-auto sm:left-6 sm:bottom-24 sm:w-[400px] sm:h-[600px] sm:rounded-3xl overflow-hidden shadow-2xl">
    <div class="flex flex-col h-full bg-[#f8faf9]">
    <!-- Header (Premium Design) -->
    <div class="bg-white border-b border-gray-100 p-5 flex items-center justify-between shadow-sm relative overflow-hidden">
        <div class="flex items-center gap-4 relative z-10">
            <div class="relative">
                <div class="w-12 h-12 bg-gradient-to-br from-[#4E3629] to-[#2f211a] rounded-2xl flex items-center justify-center text-white shadow-lg shadow-black/20 ring-4 ring-stone-100">
                    <i class="fas fa-robot text-xl"></i>
                </div>
                <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-white rounded-full flex items-center justify-center shadow-sm">
                    <div class="w-2.5 h-2.5 bg-[#c5a059] rounded-full animate-pulse"></div>
                </div>
            </div>
            <div>
                <h3 class="text-[#4E3629] font-black text-sm tracking-tight leading-none mb-1"><?= e($chatAssistantName ?? 'Valéntino Patisserié Akıllı Menü Asistanı') ?></h3>
                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider">Size yardımcı olmaya hazırım</p>
            </div>
        </div>
        
        <button onclick="toggleChat()" class="w-10 h-10 rounded-full bg-slate-50 hover:bg-slate-100 text-slate-400 hover:text-rose-500 transition-all flex items-center justify-center">
            <i class="fas fa-times text-lg"></i>
        </button>
    </div>

    <!-- Messages Area -->
    <div id="chat-messages" class="flex-1 overflow-y-auto p-5 space-y-6 scroll-smooth bg-white">
        <!-- Welcome Message -->
        <div class="flex flex-col items-start max-w-[90%] animate-fade-in">
            <div class="bg-[#f8faf9] text-[#1a241d] p-4 rounded-[24px] rounded-bl-none shadow-sm border border-gray-100/50 text-[13px] leading-relaxed font-medium">
                <?= e($chatWelcomeMessage ?? 'Merhaba! Valéntino Patisserié Akıllı Menü Asistanına hoş geldiniz. Size bugün ne önerebilirim? 😊') ?>
            </div>
            <span class="text-[9px] text-slate-300 font-bold uppercase tracking-widest mt-2 ml-1">Az önce</span>
        </div>

        <!-- Initial Category Chips -->
        <div id="quick-replies" class="flex flex-wrap gap-2 mt-4 animate-fade-in delay-200">
            <?php foreach ($categories as $cat): ?>
            <button type="button" onclick="sendQuickMessage('<?= e($cat['name']) ?>')" 
                class="bg-white hover:bg-[#4E3629] hover:text-white text-[#4E3629] text-[10px] font-black uppercase tracking-widest px-5 py-3 rounded-full border border-gray-100 shadow-sm transition-all hover:shadow-md active:scale-95">
                <?= e($cat['name']) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Typing Indicator -->
    <div id="chat-typing" class="px-5 py-3 hidden">
        <div class="flex items-center gap-1.5 bg-[#f8faf9] w-16 p-3 rounded-full shadow-sm border border-gray-50">
            <div class="w-1.5 h-1.5 bg-[#c5a059] rounded-full animate-bounce [animation-duration:0.8s]"></div>
            <div class="w-1.5 h-1.5 bg-[#c5a059] rounded-full animate-bounce [animation-duration:0.8s] [animation-delay:0.2s]"></div>
            <div class="w-1.5 h-1.5 bg-[#c5a059] rounded-full animate-bounce [animation-duration:0.8s] [animation-delay:0.4s]"></div>
        </div>
    </div>

    <!-- Input Area (Premium) -->
    <div class="p-5 bg-white border-t border-gray-50 pb-safe">
        <div class="flex items-center gap-3 bg-[#f8faf9] p-2 rounded-2xl border border-gray-100 focus-within:bg-white focus-within:border-[#4E3629] focus-within:ring-4 focus-within:ring-stone-100 transition-all duration-300">
            <input type="text" id="chat-input" placeholder="Size nasıl yardımcı olabilirim?" 
                class="flex-1 bg-transparent border-none outline-none px-3 py-2 text-sm text-[#1a241d] placeholder:text-slate-400 font-medium" autocomplete="off">
            <button onclick="sendChatMessage()" class="w-11 h-11 bg-[#4E3629] text-white rounded-xl flex items-center justify-center transition-all hover:bg-[#2f211a] active:scale-90 shadow-lg shadow-black/20">
                <i class="fas fa-paper-plane text-sm"></i>
            </button>
        </div>
    </div>
    </div>
</div>

<style>
    @keyframes fade-in {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in { animation: fade-in 0.3s ease-out forwards; }
    .delay-200 { animation-delay: 0.2s; }
    .pb-safe { padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px)); }
    
    @media (max-width: 640px) {
        #chat-modal.active {
            display: block;
            opacity: 1;
            inset: 0 !important;
            width: 100% !important;
            height: 100% !important;
            border-radius: 0 !important;
        }
    }
    #chat-modal.active {
        display: block;
        opacity: 1;
    }
    
    #chat-messages::-webkit-scrollbar {
        width: 4px;
    }
    #chat-messages::-webkit-scrollbar-track {
        background: transparent;
    }
    #chat-messages::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 10px;
    }
</style>

<script>
    const chatModal = document.getElementById('chat-modal');
    const chatMessages = document.getElementById('chat-messages');
    const chatInput = document.getElementById('chat-input');
    const chatTyping = document.getElementById('chat-typing');
    const chatFab = document.getElementById('chat-fab');
    const quickReplies = document.getElementById('quick-replies');

    function toggleChat() {
        const isActive = chatModal.classList.contains('active');
        if (isActive) {
            chatModal.classList.remove('active');
            setTimeout(() => chatModal.classList.add('hidden'), 300);
            chatFab.style.display = 'block';
        } else {
            chatModal.classList.remove('hidden');
            setTimeout(() => {
                chatModal.classList.add('active');
                chatInput.focus();
            }, 10);
            if (window.innerWidth < 640) {
                chatFab.style.display = 'none';
            }
        }
    }

    function sendQuickMessage(text) {
        chatInput.value = text;
        sendChatMessage();
        if (quickReplies) quickReplies.classList.add('hidden');
    }

    function addChatMessage(text, isBot = false, data = null) {
        const wrapper = document.createElement('div');
        wrapper.className = `flex flex-col ${isBot ? 'items-start' : 'items-end'} w-full animate-fade-in`;

        // Bot cevabında ürün önerisi varsa metin balonunu gösterme
        const hasProducts = isBot && data && data.suggested_products && data.suggested_products.length > 0;

        if (!hasProducts) {
            const msgDiv = document.createElement('div');
            if (isBot) {
                msgDiv.className = 'bg-[#f8faf9] text-[#1a241d] p-4 rounded-[24px] rounded-bl-none shadow-sm border border-gray-100/50 text-[13px] max-w-[90%] leading-relaxed font-medium';
                msgDiv.innerHTML = text.replace(/\n/g, '<br>');
            } else {
                msgDiv.className = 'bg-[#4E3629] text-white p-4 rounded-[24px] rounded-br-none shadow-lg shadow-black/15 text-[13px] max-w-[90%] leading-relaxed font-semibold';
                msgDiv.textContent = text;
            }
            wrapper.appendChild(msgDiv);
        }

        // Zaman etiketi
        const timeSpan = document.createElement('span');
        timeSpan.className = 'text-[9px] text-slate-300 font-bold uppercase tracking-widest mt-2 px-1';
        timeSpan.textContent = 'Şimdi';
        wrapper.appendChild(timeSpan);

        if (data) {
            // Önerilen Ürünler (Premium Card Design)
            if (data.suggested_products && data.suggested_products.length > 0) {
                const productsContainer = document.createElement('div');
                productsContainer.className = 'grid grid-cols-1 gap-3 mt-4 w-full max-w-[90%]';
                
                data.suggested_products.forEach(product => {
                    const card = document.createElement('div');
                    card.onclick = () => {
                        toggleChat(); 
                        if (typeof openProductModal === 'function') {
                            openProductModal(product.id);
                        }
                    };
                    card.className = 'flex items-center gap-4 bg-white p-3 rounded-[24px] border border-gray-100 shadow-md hover:shadow-xl hover:border-[#c5a059] transition-all duration-300 cursor-pointer group';
                    card.innerHTML = `
                        <div class="w-16 h-16 rounded-[18px] overflow-hidden flex-shrink-0 border border-gray-50 shadow-inner">
                            <img src="${product.image_url}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-[11px] font-black text-[#4E3629] uppercase tracking-widest truncate mb-1">${product.name}</h4>
                            <div class="flex items-center gap-2">
                                <p class="text-sm text-[#c5a059] font-black">${product.price_formatted}</p>
                                <span class="px-2 py-0.5 bg-[#c5a059]/15 text-[#6b5424] text-[8px] font-black uppercase rounded-md">Önerilen</span>
                            </div>
                        </div>
                        <div class="w-9 h-9 rounded-full bg-gray-50 flex items-center justify-center text-gray-300 group-hover:bg-[#c5a059] group-hover:text-white transition-all duration-300 shadow-sm">
                            <i class="fas fa-chevron-right text-[10px]"></i>
                        </div>
                    `;
                    productsContainer.appendChild(card);
                });
                wrapper.appendChild(productsContainer);
            }

            // Önerilen Kategoriler (Premium Chip Design)
            if (data.suggested_categories && data.suggested_categories.length > 0) {
                const catsContainer = document.createElement('div');
                catsContainer.className = 'flex flex-wrap gap-2 mt-4 w-full max-w-[90%]';
                
                data.suggested_categories.forEach(cat => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.onclick = (e) => {
                        e.preventDefault();
                        sendQuickMessage(cat.name);
                    };
                    btn.className = 'text-[10px] font-black uppercase tracking-widest bg-white text-[#4E3629] px-5 py-3 rounded-full border border-gray-100 shadow-sm hover:border-[#c5a059] hover:text-[#c5a059] hover:shadow-md transition-all active:scale-95';
                    btn.textContent = cat.name;
                    catsContainer.appendChild(btn);
                });
                wrapper.appendChild(catsContainer);
            }
        }

        chatMessages.appendChild(wrapper);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    async function sendChatMessage() {
        const text = chatInput.value.trim();
        if (!text) return;

        chatInput.value = '';
        addChatMessage(text, false);
        if (quickReplies) quickReplies.classList.add('hidden');
        
        chatTyping.classList.remove('hidden');
        chatMessages.scrollTop = chatMessages.scrollHeight;

        try {
            const response = await fetch('api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: text })
            });
            
            const data = await response.json();
            
            // Botun "yazıyor..." efektini 2-3 saniye sürdür (daha insansı hissettirir)
            const delay = Math.floor(Math.random() * (2500 - 1500 + 1)) + 1500; // 1.5 - 2.5 saniye arası
            
            setTimeout(() => {
                chatTyping.classList.add('hidden');
                if (data.success) {
                    addChatMessage(data.response, true, data);
                } else {
                    addChatMessage('Üzgünüm, şu an yanıt veremiyorum. Lütfen tekrar deneyin.', true);
                }
            }, delay);

        } catch (error) {
            chatTyping.classList.add('hidden');
            addChatMessage('Bağlantı hatası oluştu. Lütfen tekrar deneyin.', true);
        }
    }

    chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendChatMessage();
    });
</script>
