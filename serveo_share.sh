#!/bin/bash

# Share using Serveo (Free, No signup required)
# ระบบจองอุปกรณ์ Live Streaming

echo "🚀 Starting server and creating public tunnel..."
echo ""

# Start PHP server in background
php -S localhost:8080 &
SERVER_PID=$!

echo "✅ Local server started at http://localhost:8080"
echo "🌐 Creating public tunnel with Serveo..."
echo ""
echo "📋 Test Accounts:"
echo "   👨‍💼 Admin: username=admin, password=password"
echo "   👤 Customer: username=customer, password=password"
echo ""
echo "⚠️  Press Ctrl+C to stop both server and tunnel"
echo ""

# Create tunnel with Serveo
ssh -R 80:localhost:8080 serveo.net

# Kill PHP server when script ends
kill $SERVER_PID