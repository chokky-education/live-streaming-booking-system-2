#!/bin/bash

# Share Live Streaming Booking System
# ระบบจองอุปกรณ์ Live Streaming

echo "🌐 Starting Shared Live Streaming Booking System..."
echo ""
echo "📍 Local Access:"
echo "   http://localhost:8080"
echo ""
echo "🔗 Share with Friends:"
echo "   http://172.22.208.33:8080"
echo ""
echo "📱 Admin Panel:"
echo "   http://172.22.208.33:8080/pages/admin/"
echo ""
echo "📋 Test Accounts:"
echo "   👨‍💼 Admin: username=admin, password=password"
echo "   👤 Customer: username=customer, password=password"
echo ""
echo "📤 Share this URL with your friends:"
echo "   🔗 http://172.22.208.33:8080"
echo ""
echo "⚠️  Make sure your friends are on the same WiFi network"
echo "⚠️  Press Ctrl+C to stop the server"
echo ""

# Start PHP server on all interfaces
php -S 0.0.0.0:8080