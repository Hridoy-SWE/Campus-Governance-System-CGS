
package utils

import (
	"crypto/rand"
	"encoding/hex"
	"fmt"
	"time"
)

func NewSessionToken() (string, error) {
	b := make([]byte, 32)
	if _, err := rand.Read(b); err != nil {
		return "", err
	}
	return hex.EncodeToString(b), nil
}

func NewTrackingToken() (string, error) {
	b := make([]byte, 6)
	if _, err := rand.Read(b); err != nil {
		return "", err
	}
	return fmt.Sprintf("CGS-%s-%s", time.Now().Format("20060102150405"), hex.EncodeToString(b)), nil
}
