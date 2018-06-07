package main

/**
 * main
 *
 * @Author dodosss
 * @Date 2018/06/07
 */

//Go语言TCP HTTP编程

import (
	"bytes"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"os/exec"
	"strings"
	"time"
)

// 实现了接口中要求的函数ServeHTTP
type HTTPServer struct {
	req         *http.Request
	method      *HTTPMethod
	httpErr     []string
	accessToken map[string]user
}
type user struct {
	id int
	ot int64
}

// 根据请求分配操作方法
type HTTPMethod map[string]map[string]HandlerFunc

// HTTP请求执行体
type HandlerFunc func() string

func stringsToLower(s string) string {
	return strings.ToLower(s)
}

func ToJSON(s interface{}) []byte {
	if js, err := json.Marshal(s); err == nil {
		return js
	}
	return nil
}

// 当前的时间戳
func UnixTime() int64 {
	return time.Now().Unix()
}

func UnixNano() int64 {
	return time.Now().UnixNano()
}

// 取得当前日期、时间
// "2006-01-02 15:04:05"
func GetNow(tp string) string {
	t := time.Unix(UnixTime(), 0)
	return t.Format(tp)
}

func exec_shell(s string) string {
	cmd := exec.Command("/bin/bash", "-c", s)
	var out bytes.Buffer
	cmd.Stdout = &out
	err := cmd.Run()
	if err != nil {
		log.Fatal(err)
	}
	return fmt.Sprintf("%s", out.String())
}

func main() {
	NewHttpServer()

	s := "02010100AAAA0001"
	fmt.Printf("%s, %s\r\n", s[0:2], (s[0:2] == "02"))
}

// 启动HTTP服务器
// 注册需要的请求方法及函数
func NewHttpServer() {
	s := &HTTPServer{httpErr: make([]string, 0, 2), accessToken: make(map[string]user, 10)}
	s.method = &HTTPMethod{
		"POST": map[string]HandlerFunc{
			"hello": s.PostHello,
		},
		"GET": map[string]HandlerFunc{
			"hello": s.GetHello,
		},
	}
	s.httpErr = append(s.httpErr, "404!", "非法请求", "缺少参数")
	http.ListenAndServe(":8888", s)
}

// 实现了Handler
func (h *HTTPServer) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Access-Control-Allow-Origin", "*")
	w.Header().Add("Access-Control-Allow-Headers", "Content-Type") // header的类型
	w.Header().Set("content-type", "application/json")
	if r.RequestURI != "*" {
		r.ParseForm()
		h.req = r
		fmt.Fprintf(w, "%s", h.SwitchHTTPMethod())
	}
}

// 根据请求方式生成指定方法
func (h *HTTPServer) SwitchHTTPMethod() string {
	if (*(*h).method)[h.req.Method] == nil {
		return h.httpErr[1]
	}

	if f := (*(*h).method)[h.req.Method][stringsToLower(h.req.URL.Path[1:])]; f != nil {
		return f()
	}

	return h.httpErr[0]
}
func (h *HTTPServer) GetHello() string {
	jsonData := map[string]interface{}{
		"status": 1,
		"data": map[string]interface{}{
			"msg":  "hello",
			"time": GetNow("2006-01-02 15:04:05"),
		},
	}
	return string(ToJSON(jsonData))
}

func (h *HTTPServer) PostHello() string {
	jsonData := map[string]interface{}{
		"status": 1,
		"data": map[string]interface{}{
			"msg":  "POST操作成功",
			"time": GetNow("2006-01-02 15:04:05"),
		},
	}
	return string(ToJSON(jsonData))
}
