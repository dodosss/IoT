package main

import (
	"fmt"
)

// 浅谈Golang中select的用法
// https://segmentfault.com/a/1190000015036739?utm_source=tag-newest
func main() {
	ch := make(chan int, 1) // 管道
	for i := 0; i < 10; i++ {
		select {
		case ch <- i: // case对应一个channel的I/O操作
		case x := <-ch:
			fmt.Println(x)
		default:
			fmt.Printf("no one was ready to communicate\n")
		}
	}
}
