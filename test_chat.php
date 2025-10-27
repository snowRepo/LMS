<?php
// Simple test chat interface
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Chat Interface</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498DB;
            --primary-dark: #2980B9;
            --gray-200: #e5e7eb;
            --gray-500: #6b7280;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        }
        
        body {
            background-color: #f3f4f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .chat-container {
            width: 100%;
            max-width: 800px;
            height: 80vh;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .chat-header {
            background: var(--primary-color);
            color: white;
            padding: 1rem;
            text-align: center;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .chat-messages {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .message {
            max-width: 70%;
            padding: 0.5rem 0;
        }
        
        .message.sent {
            align-self: flex-end;
            text-align: right;
        }
        
        .message.received {
            align-self: flex-start;
            text-align: left;
        }
        
        .message-content {
            display: inline-block;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            font-size: 0.95rem;
            line-height: 1.4;
            max-width: 100%;
            word-wrap: break-word;
        }
        
        .sent .message-content {
            background: var(--primary-color);
            color: white;
            border-bottom-right-radius: 0.25rem;
        }
        
        .received .message-content {
            background: var(--gray-200);
            color: var(--gray-900);
            border-bottom-left-radius: 0.25rem;
        }
        
        .message-sender {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-bottom: 0.25rem;
        }
        
        .chat-input {
            padding: 1rem;
            border-top: 1px solid var(--gray-200);
            background: white;
            display: flex;
            gap: 0.5rem;
        }
        
        .chat-input textarea {
            flex: 1;
            border: 1px solid var(--gray-200);
            border-radius: 1.5rem;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            resize: none;
            outline: none;
            transition: border-color 0.2s;
        }
        
        .chat-input textarea:focus {
            border-color: var(--primary-color);
        }
        
        .send-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .send-btn:hover {
            background: var(--primary-dark);
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            Test Chat
        </div>
        
        <div class="chat-messages" id="chatMessages">
            <!-- Sample messages -->
            <div class="message received">
                <div class="message-sender">John Doe</div>
                <div class="message-content">
                    Hello! How are you doing today?
                </div>
            </div>
            
            <div class="message sent">
                <div class="message-sender">You</div>
                <div class="message-content">
                    Hi John! I'm doing great, thanks for asking. How about you?
                </div>
            </div>
            
            <div class="message received">
                <div class="message-sender">John Doe</div>
                <div class="message-content">
                    I'm doing well too! Just wanted to check in and see how the project is going.
                </div>
            </div>
            
            <div class="message sent">
                <div class="message-sender">You</div>
                <div class="message-content">
                    The project is coming along nicely. We're on track to meet the deadline.
                </div>
            </div>
        </div>
        
        <div class="chat-input">
            <textarea placeholder="Type your message..." rows="1"></textarea>
            <button class="send-btn">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
    
    <script>
        // Auto-resize textarea
        const textarea = document.querySelector('textarea');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // Scroll to bottom of messages
        const chatMessages = document.getElementById('chatMessages');
        chatMessages.scrollTop = chatMessages.scrollHeight;
    </script>
</body>
</html>
