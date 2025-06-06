// Chatbot Widget functionality
document.addEventListener('DOMContentLoaded', function() {
    const chatbotWidget = document.getElementById('chatbot-widget');
    const toggleButton = document.getElementById('chatbot-toggle');
    const messageInput = document.getElementById('chatbot-message-input');
    const sendButton = document.getElementById('chatbot-send-button');
    const messagesContainer = document.getElementById('chatbot-messages');
    const typingIndicator = document.getElementById('chatbot-typing-indicator');

    // Toggle chat visibility
    toggleButton.addEventListener('click', function() {
        chatbotWidget.classList.toggle('collapsed');
        chatbotWidget.classList.toggle('expanded');
    });

    // Send message on button click
    sendButton.addEventListener('click', sendMessage);

    // Send message on Enter key
    messageInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });

    function sendMessage() {
        const message = messageInput.value.trim();
        if (!message) return;

        // Add user message to UI immediately
        addMessage('employee', message);
        messageInput.value = '';
        
        // Show typing indicator
        typingIndicator.style.display = 'block';
        scrollToBottom();

        // Send to server
        fetch('send_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'message=' + encodeURIComponent(message)
        })
        .then(response => response.json())
        .then(data => {
            typingIndicator.style.display = 'none';
            loadMessages();
        })
        .catch(error => {
            typingIndicator.style.display = 'none';
            console.error('Error:', error);
        });
    }

    function loadMessages() {
        fetch('get_messages.php')
            .then(response => response.text())
            .then(data => {
                messagesContainer.innerHTML = data;
                scrollToBottom();
            })
            .catch(error => console.error('Error loading messages:', error));
    }

    function addMessage(sender, message) {
        const messageElement = document.createElement('div');
        messageElement.className = 'message ' + sender + '-message';
        
        const now = new Date();
        const timestamp = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        
        messageElement.innerHTML = `
            <div class="message-content">${escapeHtml(message)}</div>
            <div class="message-info">${sender === 'employee' ? 'You' : 'Support Bot'} • ${timestamp}</div>
        `;
        
        messagesContainer.appendChild(messageElement);
        scrollToBottom();
    }

    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Load messages initially and set up refresh
    loadMessages();
    setInterval(loadMessages, 3000);
});