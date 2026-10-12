#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include "../lib/ESP32QRCodeReader/src/quirc/quirc.h"
// PGM image input, using the actual bundled detector and decoder. Hardware
// PSRAM allocation becomes standard malloc only for this native host test.
int main(int argc,char** argv) {
 if(argc!=2)return 2;
 FILE* f=fopen(argv[1],"rb");if(!f)return 2;
 int w,h,max;char magic[3];
 if(fscanf(f,"%2s %d %d %d",magic,&w,&h,&max)!=4 || strcmp(magic,"P5") || max!=255 || w<=0 || h<=0 || w>4096 || h>4096)return 2;
 fgetc(f);
 struct quirc* reader=quirc_new();if(!reader || quirc_resize(reader,w,h)<0)return 2;
 unsigned char* image=quirc_begin(reader,NULL,NULL);
 if(fread(image,1,(size_t)w*h,f)!=(size_t)w*h)return 2;
 fclose(f);quirc_end(reader);
 if(quirc_count(reader)!=1){fprintf(stderr,"Expected exactly one detected QR, got %d\n",quirc_count(reader));return 1;}
 struct quirc_code code;struct quirc_data data;quirc_extract(reader,0,&code);
 quirc_decode_error_t error=quirc_decode(&code,&data);
 if(error){fprintf(stderr,"Decode failed: %s\n",quirc_strerror(error));return 1;}
 fwrite(data.payload,1,data.payload_len,stdout);fputc('\n',stdout);quirc_destroy(reader);return 0;
}
