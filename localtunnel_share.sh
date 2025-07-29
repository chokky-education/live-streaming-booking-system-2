#!/bin/bash

# Share using localhost.run (Free, No signup required)
# ระบบจองอุปกรณ์ Live Streaming

echo "🚀 Starting server and creating public tunnel..."
echo ""

# Start PHP server in background
php -S localhost:8080 &
SERVER_PID=$!

echo "✅ Local server started at http://localhost:8080"
echo "🌐 Creating public tunnel with localhost.run..."
echo ""
echo "📋 Test Accounts:"
echo "   👨‍💼 Admin: username=admin, password=password"
echo "   👤 Customer: username=customer, password=password"
echo ""
echo "⚠️  Press Ctrl+C to stop both server and tunnel"
echo ""

# Create tunnel with localhost.run
ssh -R 80:localhost:8080 localhost.run

# Kill PHP server when script ends
kill $SERVER_PID