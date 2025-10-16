<?php
/**
 * Models Index
 * ระบบจองอุปกรณ์ Live Streaming
 *
 * This file provides a centralized way to load all models
 */

// Load base model first
require_once __DIR__ . '/BaseModel.php';

// Load individual models
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/Booking.php';
require_once __DIR__ . '/Payment.php';
require_once __DIR__ . '/Package.php';
require_once __DIR__ . '/PackageItem.php';

/**
 * Model Factory Class
 */
class ModelFactory {
    private static $db = null;
    private static $models = [];

    /**
     * Initialize model factory with database connection
     */
    public static function init($db) {
        self::$db = $db;
    }

    /**
     * Get model instance
     */
    public static function getModel($modelName) {
        if (!self::$db) {
            throw new Exception('ModelFactory not initialized. Call init() first.');
        }

        if (!isset(self::$models[$modelName])) {
            $className = ucfirst($modelName);
            if (class_exists($className)) {
                self::$models[$modelName] = new $className(self::$db);
            } else {
                throw new Exception("Model class {$className} not found.");
            }
        }

        return self::$models[$modelName];
    }

    /**
     * Get all model instances
     */
    public static function getAllModels() {
        return [
            'user' => self::getModel('User'),
            'booking' => self::getModel('Booking'),
            'payment' => self::getModel('Payment'),
            'package' => self::getModel('Package'),
            'packageItem' => self::getModel('PackageItem')
        ];
    }

    /**
     * Clear model instances (for testing)
     */
    public static function clear() {
        self::$models = [];
    }
}

/**
 * Helper function to get model instance
 */
function model($modelName) {
    return ModelFactory::getModel($modelName);
}

/**
 * Helper function to initialize models
 */
function init_models($db) {
    ModelFactory::init($db);
}
?>