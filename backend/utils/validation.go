
package utils

import (
	"errors"
	"net/mail"
	"regexp"
	"strings"
)

var usernamePattern = regexp.MustCompile(`^[a-zA-Z0-9._-]{3,50}$`)

func Required(value string) bool {
	return strings.TrimSpace(value) != ""
}

func ValidEmail(value string) bool {
	_, err := mail.ParseAddress(strings.TrimSpace(value))
	return err == nil
}

func ValidUsername(value string) bool {
	return usernamePattern.MatchString(strings.TrimSpace(value))
}

func InSet(value string, allowed ...string) bool {
	value = strings.TrimSpace(strings.ToLower(value))
	for _, item := range allowed {
		if value == strings.ToLower(item) {
			return true
		}
	}
	return false
}

func TrimmedLenBetween(value string, min, max int) bool {
	n := len(strings.TrimSpace(value))
	return n >= min && n <= max
}

func ValidateReportStatus(value string) error {
	if !InSet(value, "pending", "verified", "in_progress", "resolved", "rejected", "spam") {
		return errors.New("invalid report status")
	}
	return nil
}

func ValidatePriority(value string) error {
	if !InSet(value, "low", "medium", "high", "critical") {
		return errors.New("invalid priority")
	}
	return nil
}

func ValidateRole(value string) error {
	if !InSet(value, "admin", "faculty", "department_head", "student") {
		return errors.New("invalid role")
	}
	return nil
}
