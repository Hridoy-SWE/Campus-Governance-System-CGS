
package middleware

import (
	"net/http"
	"strings"

	"campus-governance/utils"
)

func RequireRoles(roles ...string) func(http.Handler) http.Handler {
	allowed := map[string]struct{}{}
	for _, role := range roles {
		allowed[strings.ToLower(strings.TrimSpace(role))] = struct{}{}
	}

	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			user, ok := CurrentUser(r)
			if !ok {
				utils.Fail(w, http.StatusUnauthorized, "authentication required")
				return
			}
			if _, exists := allowed[strings.ToLower(user.Role)]; !exists {
				utils.Fail(w, http.StatusForbidden, "insufficient permissions")
				return
			}
			next.ServeHTTP(w, r)
		})
	}
}
