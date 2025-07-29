#!/bin/bash

# Share using Cloudflare Tunnel (Free, No signup required for temporary tunnels)
# ระบบจองอุปกรณ์ Live Streaming

echo "🚀 Starting Live Streaming Booking System..."
echo ""

# Check if cloudflared is installed
if ! command -v cloudflared &> /dev/null; then
    echo "📦 Installing Cloudflare Tunnel..."
    if command -v brew &> /dev/null; then
        brew install cloudflared
    else
        echo "❌ Please install Homebrew first: https://brew.sh"
        echo "Then run: brew install cloudflared"
        exit 1
    fi
fi

# Start PHP server in background
echo "🔧 Starting PHP server..."
php -S localhost:8080 &
SERVER_PID=$!

sleep 2

echo "✅ Local server started at http://localhost:8080"
echo "🌐 Creating public tunnel with Cloudflare..."
echo ""
echo "📋 Test Accounts:"
echo "   👨‍💼 Admin: username=admin, password=password"
echo "   👤 Customer: username=customer, password=password"
echo ""
echo "🔗 Public URL will appear below:"
echo "⚠️  Press Ctrl+C to stop both server and tunnel"
echo ""

# Create tunnel with Cloudflare
cloudflared tunnel --url http://localhost:8080

# Kill PHP server when script ends
kill $SERVER_PID 2>/dev/null