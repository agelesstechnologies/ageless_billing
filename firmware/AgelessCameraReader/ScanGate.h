#pragma once
#include <stdint.h>
#include <string.h>

// Used by the real camera consumer task, also tested on a native compiler.
// Only confirmed frames without a QR release the latch. A decode failure,
// suspended camera or timeout does not count as removal.
class ScanGate {
    char latched[36] = {0};
    bool blankStarted = false;
    uint32_t blankAt = 0;
    uint32_t lastBlankAt = 0;
    uint32_t removalMs;
public:
    explicit ScanGate(uint32_t removal = 2000):removalMs(removal) {}
    void blank(uint32_t at) {
        // A stalled camera must not turn an unobserved interval into removal.
        if(!blankStarted || (uint32_t)(at-lastBlankAt)>750){blankStarted=true;blankAt=at;}
        else if((uint32_t)(at-blankAt)>=removalMs)latched[0]=0;
        lastBlankAt=at;
    }
    void undecodableSymbol(){blankStarted=false;}
    bool observe(const char* code,uint32_t at) {
        (void)at;blankStarted=false;
        if(strlen(code)!=35 || strncmp(code,"QR-",3)!=0)return false;
        for(int i=3;i<35;i++)if(!((code[i]>='0' && code[i]<='9')||(code[i]>='A' && code[i]<='F')))return false;
        if(strcmp(latched,code)==0)return false;
        memcpy(latched,code,36);return true;
    }
};

struct CreditReply {
    bool identityMatches, requestMatches, approved, allowPulse;
    int httpStatus, pulseMs, ttlSeconds;
    uint32_t elapsedMs;
    const char* status;
};
inline bool validCredit(const CreditReply& reply) {
    return reply.identityMatches && reply.requestMatches && reply.httpStatus==200
        && reply.approved && reply.allowPulse && strcmp(reply.status,"approved")==0
        && reply.pulseMs>=50 && reply.pulseMs<=2000
        && reply.ttlSeconds>0 && reply.ttlSeconds<=30
        && reply.elapsedMs<(uint32_t)reply.ttlSeconds*1000-250;
}
