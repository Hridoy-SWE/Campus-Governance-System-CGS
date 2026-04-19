
package utils

import (
	"crypto/rand"
	"crypto/sha256"
	"crypto/subtle"
	"encoding/base64"
	"fmt"
	"strconv"
	"strings"
)

const passwordIterations = 120000

func HashPassword(plain string) (string, error) {
	if len(plain) < 8 {
		return "", fmt.Errorf("password too short")
	}

	salt := make([]byte, 16)
	if _, err := rand.Read(salt); err != nil {
		return "", err
	}

	hash := derivePasswordHash([]byte(plain), salt, passwordIterations)
	return fmt.Sprintf("sha256$%d$%s$%s",
		passwordIterations,
		base64.RawStdEncoding.EncodeToString(salt),
		base64.RawStdEncoding.EncodeToString(hash),
	), nil
}

func CheckPassword(encoded, plain string) bool {
	parts := strings.Split(encoded, "$")
	if len(parts) != 4 || parts[0] != "sha256" {
		return false
	}

	iterations, err := strconv.Atoi(parts[1])
	if err != nil || iterations <= 0 {
		return false
	}

	salt, err := base64.RawStdEncoding.DecodeString(parts[2])
	if err != nil {
		return false
	}
	expected, err := base64.RawStdEncoding.DecodeString(parts[3])
	if err != nil {
		return false
	}

	actual := derivePasswordHash([]byte(plain), salt, iterations)
	return subtle.ConstantTimeCompare(expected, actual) == 1
}

func derivePasswordHash(password, salt []byte, iterations int) []byte {
	state := append(append([]byte{}, salt...), password...)
	sum := sha256.Sum256(state)
	out := sum[:]

	for i := 1; i < iterations; i++ {
		nextInput := append(append([]byte{}, out...), salt...)
		nextInput = append(nextInput, password...)
		sum = sha256.Sum256(nextInput)
		out = sum[:]
	}

	final := make([]byte, len(out))
	copy(final, out)
	return final
}
