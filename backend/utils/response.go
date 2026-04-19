
package utils

import (
	"encoding/json"
	"net/http"
)

type APIResponse struct {
	Success bool        `json:"success"`
	Message string      `json:"message,omitempty"`
	Data    interface{} `json:"data,omitempty"`
	Errors  interface{} `json:"errors,omitempty"`
}

func WriteJSON(w http.ResponseWriter, status int, payload APIResponse) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(payload)
}

func OK(w http.ResponseWriter, data interface{}) {
	WriteJSON(w, http.StatusOK, APIResponse{Success: true, Data: data})
}

func Created(w http.ResponseWriter, message string, data interface{}) {
	WriteJSON(w, http.StatusCreated, APIResponse{Success: true, Message: message, Data: data})
}

func Fail(w http.ResponseWriter, status int, message string) {
	WriteJSON(w, status, APIResponse{Success: false, Message: message})
}

func ValidationFail(w http.ResponseWriter, errors map[string]string) {
	WriteJSON(w, http.StatusBadRequest, APIResponse{Success: false, Message: "validation failed", Errors: errors})
}
