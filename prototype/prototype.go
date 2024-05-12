package main

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"
	"time"

	"github.com/nats-io/nats.go"
	"github.com/nats-io/nats.go/jetstream"
	"github.com/nats-io/nuid"
)

type queueClient struct {
	orgn    string
	name    string
	conn    *nats.Conn
	js      jetstream.JetStream
	s       jetstream.Stream
	states  jetstream.KeyValue
	expmsgs uint64
}

func newClient(name string) *queueClient {
	res := new(queueClient)
	res.orgn = name
	res.name = strings.ToUpper(name) + "JOBS"
	return res
}

func (qc *queueClient) connect() error {
	nc, err := nats.Connect(nats.DefaultURL)
	if err != nil {
		fmt.Println(err)
		return err
	}
	qc.conn = nc
	return nil
}

func (qc *queueClient) createQueue(purge bool) (err error) {

	if qc.conn == nil {
		err = fmt.Errorf("not connected")
		fmt.Println(err)
		return err
	}

	qc.js, _ = jetstream.New(qc.conn)

	config := jetstream.StreamConfig{
		Name:        qc.name,
		Description: "Job Queue for " + qc.orgn,
		Subjects:    []string{qc.name + ".*"},
		Retention:   jetstream.RetentionPolicy(nats.WorkQueuePolicy),
		Discard:     jetstream.DiscardOld,
		Storage:     jetstream.FileStorage,
	}

	stream, err := qc.js.CreateOrUpdateStream(context.Background(), config)

	if err != nil {
		fmt.Println(err)
		return err
	}

	qc.s = stream

	if purge {
		if err = qc.purge(); err != nil {
			return err
		}
	}

	qc.expmsgs, _ = qc.msgs()

	return qc.createKVStore(purge)
}

func (qc *queueClient) createKVStore(purge bool) (err error) {
	if qc.js == nil {
		err = fmt.Errorf("js not ready")
		fmt.Println(err)
		return err
	}

	// New version supports CreateOrUpdateKeyValue
	kv, err := qc.js.KeyValue(context.Background(), qc.name+"-jobstates")

	if err == jetstream.ErrBucketNotFound {
		// TODO TTL
		kv, err = qc.js.CreateKeyValue(context.Background(), jetstream.KeyValueConfig{
			Bucket:      qc.name + "-jobstates",
			Description: "Job status for " + qc.orgn,
		})

	}

	if err != nil {
		fmt.Println(err)
		return err
	}

	if purge {

		keys, _ := kv.Keys(context.Background())

		for _, key := range keys {
			kv.Purge(context.Background(), key)
		}
		err = kv.PurgeDeletes(context.Background())

		if err != nil {
			fmt.Println(err)
			return err
		}
	}

	qc.states = kv

	return nil
}

func (qc *queueClient) submit(payload []byte) (uid string, err error) {
	uid = nuid.Next()

	fmt.Printf("SBM Job ID %s Job Context %s\n\r", uid, string(payload))

	_, err = qc.publish(uid, payload)
	return uid, err
}

func (qc *queueClient) publish(uid string, payload []byte) (pa *jetstream.PubAck, err error) {
	if qc.s == nil {
		err := fmt.Errorf("stream does not exist")
		fmt.Println(err)
		return nil, err
	}

	err = qc.setStatus(uid, "submitted")
	if err != nil {
		fmt.Println(err)
		return nil, err
	}

	pa, err = qc.js.Publish(context.Background(), qc.name+"."+uid, payload)

	if err != nil {
		qc.states.Delete(context.Background(), uid)
		fmt.Println(err)
		return nil, err
	}

	qc.expmsgs += 1

	return pa, qc.validate()

}

func (qc *queueClient) setStatus(uid string, status string) error {

	if qc.states == nil {
		err := fmt.Errorf("states kv store does not exist")
		fmt.Println(err)
		return err
	}

	if status == "finished" {
		qc.states.Delete(context.Background(), uid)
		return nil
	}

	_, err := qc.states.PutString(context.Background(), uid, status)

	return err

}

func (qc *queueClient) getStatus(uid string) (string, error) {

	if qc.states == nil {
		err := fmt.Errorf("states kv store does not exist")
		fmt.Println(err)
		return "failed", err
	}

	kve, err := qc.states.Get(context.Background(), uid)

	if err == jetstream.ErrKeyNotFound {
		return "finished", nil
	}

	if err != nil {
		return "", err
	}

	status := string(kve.Value())

	return status, nil

}

func (qc *queueClient) purge() error {
	if qc.s == nil {
		err := fmt.Errorf("stream does not exist")
		fmt.Println(err)
		return err
	}

	err := qc.s.Purge(context.Background())

	if err != nil {
		fmt.Println(err)
		return err
	}

	qc.expmsgs = 0

	return qc.validate()
}

func (qc *queueClient) msgs() (uint64, error) {
	if qc.s == nil {
		err := fmt.Errorf("stream does not exist")
		fmt.Println(err)
		return 0, err
	}

	inf, err := qc.s.Info(context.Background())
	if err != nil {
		fmt.Println(err)
		return 0, err
	}

	return inf.State.Msgs, nil
}

func (qc *queueClient) validate() error {

	if qc.s == nil {
		err := fmt.Errorf("stream was not created")
		fmt.Println(err)
		return err
	}

	actual, err := qc.msgs()

	if err != nil {
		fmt.Println(err)
		return err
	}

	if qc.expmsgs != actual {
		return fmt.Errorf("expected %d messages, but exist %d messages", qc.expmsgs, actual)
	}

	return nil
}

func (qc *queueClient) delete() error {

	qc.expmsgs = 0

	if qc.s == nil {
		return nil
	}

	err := qc.js.DeleteStream(context.Background(), qc.name)
	qc.js.DeleteKeyValue(context.Background(), qc.states.Bucket())

	qc.s = nil
	qc.js = nil
	qc.states = nil

	if err != nil {
		fmt.Println(err)
		return err
	}

	return nil
}

type Job struct {
	Handler string          `json:"handler"`
	Content json.RawMessage `json:"content,omitempty"`
}

type submitter struct {
	qc *queueClient
}

func newsubmitter(qc *queueClient) *submitter {
	return &submitter{qc}
}

func (sbm *submitter) submit(job *Job) (string, error) {
	raw, err := json.Marshal(job)
	if err != nil {
		fmt.Println(err)
		return "", err
	}

	return sbm.qc.submit(raw)
}

func (sbm *submitter) isSubmitted(jid string) (bool, error) {
	return sbm.checkStatus(jid, "submitted")
}

func (sbm *submitter) isInProcess(jid string) (bool, error) {
	return sbm.checkStatus(jid, "inprocess")
}

func (sbm *submitter) isFinished(jid string) (bool, error) {
	return sbm.checkStatus(jid, "finished")
}

func (sbm *submitter) isFailed(jid string) (bool, error) {
	return sbm.checkStatus(jid, "failed")
}

func (sbm *submitter) checkStatus(jid string, status string) (bool, error) {
	if sbm.qc.states == nil {
		return false, fmt.Errorf("states store does not exist")
	}
	st, err := sbm.qc.getStatus(jid)
	if err != nil {
		return false, err
	}

	return st == status, err
}

type JobProcessor func(job *Job) error

type ProcessedJob struct {
	Job
	Jid string
	Raw jetstream.Msg
}

type worker struct {
	qc        *queueClient
	c         jetstream.Consumer
	processor JobProcessor
}

func newworker(qc *queueClient, proc JobProcessor) *worker {
	res := worker{}
	res.qc = qc
	res.processor = proc
	return &res
}

func (wrk *worker) processNext(to time.Duration) error {
	if err := wrk.createConsumer(); err != nil {
		return err
	}

	msgs, err := wrk.c.Fetch(1, jetstream.FetchMaxWait(to))
	if err != nil {
		fmt.Println(err)
		return err
	}

	var job jetstream.Msg

	for nextm := range msgs.Messages() {
		job = nextm
	}

	return wrk.processJob(job)
}

func (wrk *worker) processJob(job jetstream.Msg) error {

	if job == nil {
		return nil
	}

	subject := job.Subject()

	jid := strings.TrimLeft(subject, wrk.qc.name+".")

	fmt.Printf("WRK Job ID %s Job Context %s\n\r", jid, string(job.Data()))

	err := job.Ack()
	return err
}

func (wrk *worker) createConsumer() error {
	if wrk.c != nil {
		return nil
	}

	c, err := wrk.qc.js.CreateOrUpdateConsumer(context.Background(), wrk.qc.name, jetstream.ConsumerConfig{
		Name:            wrk.qc.name,
		Durable:         wrk.qc.name,
		Description:     "Worker processes for " + wrk.qc.orgn,
		DeliverPolicy:   jetstream.DeliverAllPolicy,
		AckPolicy:       jetstream.AckExplicitPolicy,
		MaxRequestBatch: 1,
		MaxAckPending:   -1,
	})

	if err != nil {
		fmt.Println(err)
		return err
	}

	wrk.c = c
	return nil
}

type reader struct {
	qc *queueClient
}

func newreader(qc *queueClient) *reader {
	res := reader{}
	res.qc = qc
	return &res
}

func (rdr *reader) readall() (map[string]Job, error) {

	if rdr.qc.s == nil {
		err := fmt.Errorf("stream was not created")
		fmt.Println(err)
		return nil, err
	}

	cons, err := rdr.qc.js.CreateOrUpdateConsumer(context.Background(), rdr.qc.name, jetstream.ConsumerConfig{
		AckPolicy:     jetstream.AckNonePolicy,
		DeliverPolicy: jetstream.DeliverAllPolicy,
	})
	if err != nil {
		fmt.Println(err)
		return nil, err
	}

	msgs, err := cons.FetchNoWait(int(rdr.qc.expmsgs))

	if err != nil {
		fmt.Println(err)
		return nil, err
	}

	if msgs.Error() != nil {
		err = msgs.Error()
		fmt.Println(err)
		return nil, err
	}

	jobs := make(map[string]Job)

	for msg := range msgs.Messages() {
		subject := msg.Subject()
		raw := msg.Data()

		var job Job

		err = json.Unmarshal(raw, &job)

		if err != nil {
			fmt.Println(err)
			return nil, err
		}

		fmt.Println(subject, " ", job)
	}

	return jobs, err
}

func main() {

	queuename := "yii3-queue"

	qc := newClient(queuename)

	defer qc.delete()

	sbm := newsubmitter(qc)

	jobs := []Job{{"sendmail", []byte("1111111111111111")}, {"deletefiles", []byte("2222222222222222")}}

	var err error

	submitted := make(map[string]Job)

	for {
		if err = qc.connect(); err != nil {
			break
		}

		if err = qc.createQueue(true); err != nil {
			break
		}

		for _, job := range jobs {
			uid, err := sbm.submit(&job)
			if err != nil {
				break
			}
			submitted[uid] = job
		}

		for uid, _ := range submitted {
			subm, err := sbm.isSubmitted(uid)
			if err != nil {
				break
			}
			if !subm {
				err = fmt.Errorf("wrong status for job %s", uid)
				fmt.Println(err)
				break
			}
		}

		wrk := newworker(qc, func(job *Job) error {
			return nil
		})

		for range submitted {
			if err := wrk.processNext(1 * time.Second); err != nil {
				fmt.Println(err)
				break
			}
		}

		break
	}

	return
}
