package main

/**
 * main
 *
 * @Author dodosss
 * @Date 2018/06/07
 */

//Go语言TCP Socket编程
//https://tonybai.com/2015/11/17/tcp-programming-in-golang/
import (
	"fmt"
	"net"
	"sync"
	"time"
)

var (
	// TCP连接队列
	conns = NewConns()
)

// 当前的时间戳
func UnixTime() int64 {
	return time.Now().Unix()
}

// 对比时间戳
func IsDiffTimeIn(t, diff int64) bool {
	if UnixTime()-t >= diff {
		return true
	}
	return false
}

// 删除切片中某个元素
func removeConn(list []*conn, i int) []*conn {
	return append(list[:i], list[i+1:]...)
}

///////////////队列开始/////////////////
type Conns struct {
	conns []*conn
	lk    sync.Mutex
}

// 每个TCP连接属性
type conn struct {
	tcp   *net.Conn
	ctime int64
}

// 初始化TCP连接类
func NewConns() *Conns {
	return &Conns{}
}

// 增加一个连接到队列
func (s *Conns) Add(c *net.Conn) {
	s.lk.Lock()
	defer s.lk.Unlock()
	s.conns = append(s.conns, &conn{tcp: c, ctime: UnixTime()})
}

// 删除指定连接
func (s *Conns) DelConn(c *conn) {
	s.lk.Lock()
	defer s.lk.Unlock()
	for k, v := range s.conns {
		if v.tcp == c.tcp {
			s.conns = removeConn(s.conns, k)
			return
		}
	}
}

// 取得所有连接
func (s *Conns) GetConns() []*conn {
	return s.conns
}

// 取得连接
func (s *Conns) GetConn(c *net.Conn) *conn {
	all := s.GetConns()
	for _, v := range all {
		fmt.Printf("GetConn.(%x,  %T)\n\r", v, v)
		if v.tcp == c {
			return v
		}
	}
	return nil
}

// 取得当前连接TCP连接内存
func (c *conn) GetTcp() *net.Conn {
	return c.tcp
}

// 取得当前连接最近修改时间
func (c *conn) GetCtime() int64 {
	return c.ctime
}

// 为当前连接续期
func (c *conn) AddDuration() {
	c.ctime = UnixTime()
}

///////////////队列结束/////////////////

func handleConn(conn *net.Conn) {
	defer (*conn).Close()
	for {
		// read from the connection
		// ... ...
		// write to the connection
		//... ...
		buf := make([]byte, 256)
		n, err := (*conn).Read(buf)
		if err != nil {
			fmt.Printf("Tcp killed by client, %s.(%x,  %T)\n\r", err.Error(), conn, conn)
			return
		}
		bufs := string(buf[:n])
		if bufs == "\n" {
			continue
		}
		// 当前连接续期
		c := conns.GetConn(conn)
		fmt.Printf("AddDuration.(%x,  %T)\n\r", conn, conn)
		fmt.Printf("AddDuration.(%x,  %T)\n\r", c, c)
		if c != nil {
			c.AddDuration()
			fmt.Printf("AddDuration.(%x,  %T)\n\r", conn, conn)
		}
		(*conn).Write([]byte(bufs))
		fmt.Printf("%x,  %T\r\n", bufs, bufs)
	}
}

func waiting() {
	for {

		all := conns.GetConns()

		fmt.Printf("online:%d\r\n", len(all))

		for _, conn := range all {
			if IsDiffTimeIn(conn.GetCtime(), 10) {
				tcp := conn.GetTcp()
				conns.DelConn(conn) // 删除队列连接
				(*tcp).Write([]byte("good bye."))
				(*tcp).Close() // 主动关闭连接
				fmt.Printf("Tcp killed by server.(%x,  %T)\n\r", tcp, tcp)
			}
		}

		time.Sleep(1 * time.Second)
	}
}

func listen() {
	tcp, err := net.Listen("tcp", "0.0.0.0:8888")
	if err != nil {
		fmt.Println(err.Error())
		return
	}
	defer tcp.Close()
	for {
		conn, err := tcp.Accept()
		if err != nil {
			fmt.Println(err.Error())
			conn.Close()
			continue
		}
		fmt.Printf("New conn.(%x,  %T)\n\r", &conn, &conn)
		conns.Add(&conn)
		go handleConn(&conn)
	}
}

func main() {
	go listen()
	waiting()
}
