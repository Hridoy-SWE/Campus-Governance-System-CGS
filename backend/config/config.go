package config

import (
	"os"
	"strconv"
	"strings"

	"github.com/joho/godotenv"
)

type Config struct {
	Port            string
	DatabasePath    string
	UploadDir       string
	AppEnv          string
	SessionSecret   string
	FrontendOrigin  string
	SessionTTLHours int
}

func getEnv(key, fallback string) string {
	value := strings.TrimSpace(os.Getenv(key))
	if value == "" {
		return fallback
	}
	return value
}

func getEnvInt(key string, fallback int) int {
	raw := strings.TrimSpace(os.Getenv(key))
	if raw == "" {
		return fallback
	}

	value, err := strconv.Atoi(raw)
	if err != nil || value <= 0 {
		return fallback
	}

	return value
}

func Load() Config {
	// 🔴 THIS IS THE CRITICAL FIX
	// Load .env file automatically
	if err := godotenv.Load(".env"); err != nil {
		// optional: log warning (not fatal)
	}
	return Config{
		Port:            getEnv("PORT", "8080"),
		DatabasePath:    getEnv("DATABASE_PATH", "../database/campus.db"),
		UploadDir:       getEnv("UPLOAD_DIR", "./uploads"),
		AppEnv:          getEnv("APP_ENV", "development"),
		SessionSecret:   getEnv("SESSION_SECRET", "change_this_secret"),
		FrontendOrigin:  getEnv("FRONTEND_ORIGIN", "http://127.0.0.1:5500"),
		SessionTTLHours: getEnvInt("SESSION_TTL_HOURS", 24),
	}
}
